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
class ReconstitutingScope extends Scope
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
        // During an explicit replay the Broker feeds events in order, so we
        // must not reconstitute (it would double-apply the same events).
        if ($this->replaying) {
            return parent::load($type, $id);
        }

        $any_miss = false;

        $states = collect(is_iterable($id) ? $id : [$id])
            ->map(function ($one) use ($type, &$any_miss) {
                if ($cached = $this->fromCache($type, $one)) {
                    return $cached;
                }

                $any_miss = true;

                return $this->fromStorage($type, $one);
            });

        if ($any_miss && $this->isStale($states)) {
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

    protected function fromStorage(string $type, Bits|UuidInterface|AbstractUid|int|string|null $id): State
    {
        $snapshot = $id === null
            ? $this->snapshots->loadSingleton($type)
            : $this->snapshots->load(Id::from($id), $type);

        return $snapshot instanceof State
            ? $this->cache->put($snapshot)
            : $this->make($type, $id);
    }

    /**
     * A requested state is stale when an event exists for it that is newer than
     * the position its snapshot (or blank state) was last advanced to.
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
                        $query->where('state_id', $state->id);
                    });
                }
            })
            ->groupBy('state_type', 'state_id')
            ->get()
            ->keyBy(fn ($row) => $row->state_type.':'.$row->state_id);

        foreach ($states as $state) {
            $max = $latest->get($state::class.':'.$state->id)?->max_event_id;

            if ($max === null) {
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
     * updated in place (preserving their identity), and any related states are
     * inserted only if absent—never overwriting a live singleton.
     *
     * @param  Collection<int, State>  $states
     */
    protected function reconstitute(Collection $states): void
    {
        $summary = AggregateStateSummary::summarize(...$states->all());

        $rebuilt = new Scope(new InMemoryCache);

        (new Replay(
            states: $rebuilt,
            events: $summary->events(),
            phases: new Phases(Phase::Apply),
        ))->handle();

        $requested = $states->map(fn (State $state) => $state::class.':'.$state->id)->all();

        foreach ($rebuilt->all() as $state) {
            $live = $this->cache->get($state::class, $state->id);

            if (in_array($state::class.':'.$state->id, $requested, true)) {
                $this->merge($state, $live);
            } elseif ($live === null) {
                $this->cache->put($state);
            }
        }
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
