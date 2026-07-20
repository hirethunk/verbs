<?php

namespace Thunk\Verbs\State;

use Glhd\Bits\Bits;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Uid\AbstractUid;
use Thunk\Verbs\Contracts\StoresEvents;
use Thunk\Verbs\Contracts\StoresSnapshots;
use Thunk\Verbs\Event;
use Thunk\Verbs\Exceptions\SeedInvariantViolation;
use Thunk\Verbs\Facades\Id;
use Thunk\Verbs\Lifecycle\Lifecycle;
use Thunk\Verbs\Lifecycle\Phase;
use Thunk\Verbs\Lifecycle\Phases;
use Thunk\Verbs\Lifecycle\Queue as EventQueue;
use Thunk\Verbs\Lifecycle\ReplayMode;
use Thunk\Verbs\SingletonState;
use Thunk\Verbs\State;
use Thunk\Verbs\State\Cache\Contracts\ReadableCache;
use Thunk\Verbs\State\Cache\Contracts\WritableCache;
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
        protected EventQueue $queue,
        protected ReplayMode $replay_mode,
        WritableCache&ReadableCache $cache,
    ) {
        // Placeholder until the ReconstitutingResolver extraction: this
        // subclass still overrides every path that would consult the resolver.
        parent::__construct($cache, new RebuildResolver);
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

    /**
     * Cache hits are request-stable by design: within a request you compute
     * against one consistent view of each state. refresh() is the explicit
     * "ask otherwise"—it always runs the staleness check and brings the *same
     * instance* up to date, even if the identity map was reset (e.g. by a
     * replay) since the caller got its reference. The one exception is a state
     * with queued-but-uncommitted events: its in-memory view already includes
     * applies that storage doesn't, so refresh() never overwrites it (see
     * harvest() and adopt()) and a conflicting newer write by someone else
     * surfaces at commit as a ConcurrencyException.
     */
    public function refresh(State $state): State
    {
        $canonical = $this->adopt($state);

        $states = collect([$canonical]);

        if (! $this->replaying && $this->isStale($states)) {
            $this->reconstitute($states);
        }

        if ($canonical !== $state) {
            if ($this->queue->hasEventsFor($state)) {
                // The caller's instance carries queued-but-uncommitted applies
                // that the canonical instance doesn't—syncing would silently
                // revert them (same rule as harvest()).
                Log::debug('Verbs: skipped syncing a state with uncommitted events from its canonical instance.', [
                    'state_type' => $state::class,
                    'state_id' => $state->id,
                ]);
            } else {
                // Another instance owns this identity (the cache was reset and
                // the identity reloaded behind this reference). Sync the
                // caller's instance from it rather than ever throwing.
                $this->merge($canonical, $state);

                Log::debug('Verbs: refreshed a state whose identity is now owned by a different instance.', [
                    'state_type' => $state::class,
                    'state_id' => $state->id,
                ]);
            }
        }

        return $state;
    }

    /**
     * Resolve which instance canonically owns this state's identity, re-adopting
     * the given instance (seeded from its latest snapshot) if the cache has no
     * entry—which is what happens after a replay reset clears the scope.
     */
    protected function adopt(State $state): State
    {
        $cached = $this->cache->get($state::class, $this->cacheId($state));

        if ($cached !== null) {
            return $cached;
        }

        // A state with queued-but-uncommitted events keeps its in-memory view:
        // seeding it from the (necessarily older) snapshot would silently
        // revert applies that are still on their way to storage.
        if (! $this->queue->hasEventsFor($state) && ($snapshot = $this->latestSnapshotFor($state))) {
            $this->merge($snapshot, $state);
        }

        return $this->cache->put($state);
    }

    protected function latestSnapshotFor(State $state): ?State
    {
        if ($state instanceof SingletonState) {
            return $this->snapshots->loadSingleton($state::class);
        }

        return Id::tryFrom($state->id) === null
            ? null
            : $this->snapshots->load(Id::from($state->id), $state::class);
    }

    protected function fromCache(string $type, Bits|UuidInterface|AbstractUid|int|string|null $id): ?State
    {
        return $this->cache->get($type, $this->cacheId($type, $id));
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
     * the last event id its snapshot (or blank baseline) had applied. Singletons
     * are matched by type only, mirroring how their events are stored and read.
     *
     * @param  Collection<int, State>  $states
     */
    protected function isStale(Collection $states): bool
    {
        return $this->events->hasUnappliedEvents(
            $states->map(fn (State $state) => new StateIdentity(
                state_type: $state::class,
                state_id: Id::from($state->id),
                last_event_id: $state->last_event_id,
            )),
        );
    }

    /**
     * Bring the requested states up to date by replaying their connected
     * component inside an isolated scope. When every member's snapshot sits at
     * the window floor, members are seeded from their snapshots and only the
     * window replays—exactly equivalent to (and much cheaper than) rebuilding
     * the whole component from blank, which remains the fallback whenever
     * anything about the snapshots is murky.
     *
     * @param  Collection<int, State>  $states
     */
    protected function reconstitute(Collection $states): void
    {
        $started_at = microtime(true);

        $plan = ReconstitutionPlan::plan(
            $states,
            use_snapshots: config('verbs.reconstitution_uses_snapshots', true),
        );

        if (! $this->rebuild($states, $plan)) {
            // The seeded attempt met something unexpected (a seed vanished, or
            // a window event was already absorbed by a seed). Degrade to the
            // always-correct blank baseline rather than ever double-applying.
            $plan = ReconstitutionPlan::plan($states, use_snapshots: false);

            $this->rebuild($states, $plan);
        }

        $this->diagnostics($plan, $states, $started_at);
    }

    protected function rebuild(Collection $states, ReconstitutionPlan $plan): bool
    {
        $seeds = $plan->seeded ? $plan->seeds() : new Collection;

        if ($seeds === null) {
            Log::warning('Verbs: a snapshot disappeared between planning and seeding; rebuilding from a blank baseline.', [
                'requested' => $states->map($this->identityKey(...))->all(),
            ]);

            return false;
        }

        $rebuilt = StateManager::rebuilding();

        foreach ($seeds as $seed) {
            $rebuilt->cache->put($seed);
        }

        try {
            // Re-applying history counts as replaying for userland guards:
            // an unlessReplaying() side effect inside apply() must not
            // re-fire every time a stale state rebuilds.
            $this->replay_mode->whileRebuilding(fn () => $rebuilt->run(function () use ($plan) {
                foreach ($plan->events() as $event) {
                    if ($plan->seeded) {
                        $this->guardSeedInvariant($event);
                    }

                    Lifecycle::run($event, new Phases(Phase::Apply));
                }
            }));
        } catch (SeedInvariantViolation $violation) {
            // Belt and braces: a probe bug (or a snapshot that advanced under
            // us) must degrade to slow-and-correct, never to double-apply.
            Log::warning('Verbs: seeded rebuild met an already-absorbed event; rebuilding from a blank baseline.', [
                'event_id' => $violation->event->id,
                'state' => $this->identityKey($violation->state),
            ]);

            return false;
        }

        $this->harvest($states, $rebuilt);

        return true;
    }

    /**
     * In a seeded rebuild, every state an event touches must still be *behind*
     * that event—a state at or past it means its seed already absorbed the
     * event, and applying it again would double-apply.
     */
    protected function guardSeedInvariant(Event $event): void
    {
        foreach ($event->states() as $state) {
            $last_event_id = Id::tryFrom($state->last_event_id);

            if ($last_event_id !== null && $last_event_id >= Id::from($event->id)) {
                throw new SeedInvariantViolation($event, $state);
            }
        }
    }

    /**
     * Merge the rebuilt results back: the states the caller asked for are
     * updated in place (preserving the very instance we return), and any
     * related states are inserted only if absent—never overwriting a live
     * singleton.
     *
     * @param  Collection<int, State>  $states
     */
    protected function harvest(Collection $states, StateManager $rebuilt): void
    {
        $requested = $states->keyBy($this->identityKey(...));

        foreach ($rebuilt->all() as $state) {
            if ($live = $requested->get($this->identityKey($state))) {
                // A rebuild only sees committed events, so a live state with
                // queued-but-uncommitted events already reflects applies the
                // rebuilt value can't—merging would silently discard them
                // (and could advance last_event_id past a foreign write,
                // defeating the commit-time concurrency guard). Keep the live
                // view; a real conflict surfaces as a ConcurrencyException.
                if ($this->queue->hasEventsFor($live)) {
                    Log::debug('Verbs: skipped merging reconstituted data over a state with uncommitted events.', [
                        'state_type' => $live::class,
                        'state_id' => $live->id,
                    ]);

                    continue;
                }

                $this->merge($state, $live);
            } elseif ($this->cache->get($state::class, $this->cacheId($state)) === null) {
                $this->cache->put($state);
            }
        }
    }

    protected function diagnostics(ReconstitutionPlan $plan, Collection $states, float $started_at): void
    {
        $context = [
            'mode' => $plan->seeded ? 'seeded' : 'blank',
            'members' => $plan->members->count(),
            'window' => $plan->window->count(),
            'floor' => $plan->floor,
            'duration_ms' => round((microtime(true) - $started_at) * 1000, 2),
            'requested' => $states->map($this->identityKey(...))->all(),
        ];

        Log::debug('Verbs: reconstituted state component.', $context);

        if ($plan->window->count() > 10_000) {
            Log::warning('Verbs: reconstitution replayed a very large event window.', $context);
        }
    }

    /**
     * A rebuilt singleton carries a different incidental id than the live one,
     * so harvest matching must go through the canonical identity key—otherwise
     * a rebuilt singleton would never match the live one and would clobber it
     * under a divergent id.
     */
    protected function identityKey(State $state): string
    {
        return StateIdentity::from($state)->key();
    }
}
