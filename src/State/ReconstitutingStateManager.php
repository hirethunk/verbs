<?php

namespace Thunk\Verbs\State;

use Glhd\Bits\Bits;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Uid\AbstractUid;
use Thunk\Verbs\Contracts\StoresEvents;
use Thunk\Verbs\Contracts\StoresSnapshots;
use Thunk\Verbs\Facades\Id;
use Thunk\Verbs\Lifecycle\AggregateStateSummary;
use Thunk\Verbs\Lifecycle\Phase;
use Thunk\Verbs\Lifecycle\Phases;
use Thunk\Verbs\Models\VerbStateEvent;
use Thunk\Verbs\SingletonState;
use Thunk\Verbs\State;
use Thunk\Verbs\State\Cache\Contracts\ReadableCache;
use Thunk\Verbs\State\Cache\Contracts\WritableCache;
use Thunk\Verbs\State\Cache\InMemoryCache;
use Thunk\Verbs\Support\Replay;
use Thunk\Verbs\Support\StateCollection;

/**
 * The request-bound scope. On a cache miss it brings the requested state(s)
 * up to date by replaying their connected component of events—so states that
 * are read by one another's apply() methods always advance in lockstep from a
 * common baseline, rather than each racing independently to "now."
 */
class ReconstitutingStateManager extends StateManager
{
    public function __construct(
        protected StoresEvents $events,
        protected StoresSnapshots $snapshots,
        WritableCache&ReadableCache $cache,
    ) {
        parent::__construct($cache);
    }

    public function load(string $type, Bits|UuidInterface|AbstractUid|iterable|int|string|null $id): StateCollection|State
    {
        [$type, $id] = $this->normalizeLoadArguments($type, $id);

        $any_miss = false;

        // For a many-state load, hydrate every missing snapshot in one query up
        // front rather than one query per state below. Singletons never take
        // this path—their snapshot is keyed by type, not id.
        $snapshots = is_iterable($id) && ! is_a($type, SingletonState::class, true)
            ? $this->snapshotsForMisses($type, $id)
            : null;

        $states = collect(is_iterable($id) ? $id : [$id])
            ->map(function ($one) use ($type, &$any_miss, $snapshots) {
                if ($cached = $this->fromCache($type, $one)) {
                    return $cached;
                }

                $any_miss = true;

                if ($snapshots !== null) {
                    $snapshot = $snapshots->get((string) Id::tryFrom($one));

                    return $snapshot ? $this->cache->put($snapshot) : $this->make($type, $one);
                }

                return $this->fromStorage($type, $one);
            });

        // During an explicit replay the Broker feeds every event in order, so we
        // only hydrate from snapshots (above) and never reconstitute here—doing so
        // would double-apply events. Hydrating from snapshots is still required so
        // that a state evicted mid-replay reloads from its snapshot, not as a blank.
        if (! $this->replaying && $any_miss && $this->isStale($states)) {
            $this->reconstitute($states);
        }

        return is_iterable($id)
            ? StateCollection::make($states)
            : $states->first();
    }

    protected function fromCache(string $type, Bits|UuidInterface|AbstractUid|int|string|null $id): ?State
    {
        return $this->cache->get($type, Id::tryFrom($id));
    }

    /** @return Collection<string, State> */
    protected function snapshotsForMisses(string $type, iterable $ids): Collection
    {
        $missing = collect($ids)
            ->map(fn ($id) => Id::tryFrom($id))
            ->filter(fn ($id) => $id !== null && ! $this->cache->has($type, $id))
            ->unique()
            ->values();

        if ($missing->isEmpty()) {
            return new Collection;
        }

        return collect($this->snapshots->load($missing->all(), $type))
            ->keyBy(fn (State $state) => (string) $state->id);
    }

    protected function fromStorage(string $type, Bits|UuidInterface|AbstractUid|int|string|null $id): State
    {
        // Singleton-ness is a property of the *type*, not of whether an id was
        // passed—keying off a null id would route a keyed state loaded with a
        // null key into loadSingleton(). A keyed state with no id falls through
        // to make(), which fails loudly on the missing key.
        if (is_a($type, SingletonState::class, true)) {
            $snapshot = $this->snapshots->loadSingleton($type);
        } else {
            $snapshot = Id::tryFrom($id) === null
                ? null
                : $this->snapshots->load(Id::from($id), $type);
        }

        return $snapshot instanceof State
            ? $this->cache->put($snapshot)
            : $this->make($type, $id);
    }

    /**
     * A requested state is stale when an event exists for it that is newer than
     * the position its snapshot (or blank state) was last advanced to. Singletons
     * are matched by type only, mirroring how their events are stored and read.
     *
     * @param  Collection<int, State>  $states
     */
    protected function isStale(Collection $states): bool
    {
        $latest = VerbStateEvent::query()
            ->toBase()
            ->select(['state_type', 'state_id', DB::raw('max(event_id) as max_event_id')])
            ->where(function ($query) use ($states) {
                foreach ($states as $state) {
                    $query->orWhere(function ($query) use ($state) {
                        $query->where('state_type', $state::class);

                        if (! $state instanceof SingletonState) {
                            $query->where('state_id', $state->id);
                        }
                    });
                }
            })
            ->groupBy('state_type', 'state_id')
            ->get();

        foreach ($states as $state) {
            $rows = $state instanceof SingletonState
                ? $latest->where('state_type', $state::class)
                : $latest->where('state_type', $state::class)->where('state_id', $state->id);

            $max = $rows->max('max_event_id');

            if (! $max) {
                continue;
            }

            $applied = $state->last_event_id ? (int) Id::from($state->last_event_id) : 0;

            if ((int) $max > $applied) {
                return true;
            }
        }

        return false;
    }

    /**
     * Rebuild the connected component of the requested states inside an isolated
     * scope, then merge the results back: the states the caller asked for are
     * updated in place (preserving the very instance we return), and any related
     * states are inserted only if absent—never overwriting a live singleton.
     *
     * @param  Collection<int, State>  $states
     */
    protected function reconstitute(Collection $states): void
    {
        $summary = AggregateStateSummary::summarize(...$states->all());

        $rebuilt = new StateManager(new InMemoryCache);

        (new Replay(
            states: $rebuilt,
            events: $summary->events(),
            phases: new Phases(Phase::Apply),
        ))->handle();

        $requested = $states->keyBy($this->identityKey(...));

        foreach ($rebuilt->all() as $state) {
            if ($live = $requested->get($this->identityKey($state))) {
                $this->merge($state, $live);
            } elseif ($this->cache->get($state::class, $this->cacheId($state)) === null) {
                $this->cache->put($state);
            }
        }
    }

    /**
     * A singleton is identified by type, not id (its in-memory id is incidental
     * and differs between the live scope and a blank rebuild scope), so we key it
     * the same way the cache does—otherwise a rebuilt singleton would never match
     * the live one and would clobber it under a divergent id.
     */
    protected function identityKey(State $state): string
    {
        return $state::class.':'.$this->cacheId($state);
    }

    protected function cacheId(State $state): int|string|null
    {
        return $state instanceof SingletonState ? null : $state->id;
    }

    protected function merge(State $from, State $into): void
    {
        foreach (get_object_vars($from) as $property => $value) {
            if ($property !== 'id') {
                $into->{$property} = $value;
            }
        }
    }
}
