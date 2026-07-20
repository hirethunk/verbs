<?php

namespace Thunk\Verbs\State;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Thunk\Verbs\Contracts\StoresEvents;
use Thunk\Verbs\Contracts\StoresSnapshots;
use Thunk\Verbs\Event;
use Thunk\Verbs\Exceptions\SeedInvariantViolation;
use Thunk\Verbs\Facades\Id;
use Thunk\Verbs\Lifecycle\Lifecycle;
use Thunk\Verbs\Lifecycle\Phase;
use Thunk\Verbs\Lifecycle\Phases;
use Thunk\Verbs\Lifecycle\Queue as EventQueue;
use Thunk\Verbs\State;

/**
 * hydrate-on-miss: snapshot · advance-on-stale: yes
 *
 * The default, request-bound policy. On a cache miss it hydrates from the
 * latest snapshot; on a stale state it brings the requested state(s) up to
 * date by replaying their connected component of events inside an isolated
 * rebuild scope—so states that are read by one another's apply() methods
 * always advance in lockstep from a common baseline, rather than each racing
 * independently to "now."
 */
class ReconstitutingResolver implements StateResolver
{
    use HydratesFromSnapshots;

    public function __construct(
        protected StoresEvents $events,
        protected StoresSnapshots $snapshots,
        protected EventQueue $queue,
    ) {}

    public function reconcile(StateManager $memory, Collection $states): void
    {
        if (! $this->isStale($states)) {
            return;
        }

        $this->reconstitute($memory, $states);
    }

    public function hasUncommittedEvents(State $state): bool
    {
        return $this->queue->hasEventsFor($state);
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
    protected function reconstitute(StateManager $memory, Collection $states): void
    {
        $started_at = microtime(true);

        $plan = ReconstitutionPlan::plan(
            $states,
            use_snapshots: config('verbs.reconstitution_uses_snapshots', true),
        );

        if (! $this->rebuild($memory, $states, $plan)) {
            // The seeded attempt met something unexpected (a seed vanished, or
            // a window event was already absorbed by a seed). Degrade to the
            // always-correct blank baseline rather than ever double-applying.
            $plan = ReconstitutionPlan::plan($states, use_snapshots: false);

            $this->rebuild($memory, $states, $plan);
        }

        $this->diagnostics($plan, $states, $started_at);
    }

    protected function rebuild(StateManager $memory, Collection $states, ReconstitutionPlan $plan): bool
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
            $this->reapply($rebuilt, $plan);
        } catch (SeedInvariantViolation $violation) {
            // Belt and braces: a probe bug (or a snapshot that advanced under
            // us) must degrade to slow-and-correct, never to double-apply.
            Log::warning('Verbs: seeded rebuild met an already-absorbed event; rebuilding from a blank baseline.', [
                'event_id' => $violation->event->id,
                'state' => $this->identityKey($violation->state),
            ]);

            return false;
        }

        $this->harvest($memory, $states, $rebuilt);

        return true;
    }

    /**
     * Drive the plan's window through the rebuild scope. While that scope is
     * bound, userland unlessReplaying() guards suppress structurally—its
     * resolver re-applies history—so a side effect inside apply() can't
     * re-fire every time a stale state rebuilds.
     *
     * This inlined select→scope→apply loop is a deliberate way station:
     * Broker::replay() and VerifyCommand::rebuild() are the same shape, and
     * when a first-class Replay unit is extracted, this loop migrates into it.
     */
    protected function reapply(StateManager $rebuilt, ReconstitutionPlan $plan): void
    {
        $rebuilt->run(function () use ($plan) {
            foreach ($plan->events() as $event) {
                if ($plan->seeded) {
                    $this->guardSeedInvariant($event);
                }

                Lifecycle::run($event, new Phases(Phase::Apply));
            }
        });
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
     * Merge the rebuilt results back into the *outer* scope: the states the
     * caller asked for are updated in place (preserving the very instance the
     * caller holds), and any related states are inserted only if absent—never
     * overwriting a live singleton.
     *
     * @param  Collection<int, State>  $states
     */
    protected function harvest(StateManager $memory, Collection $states, StateManager $rebuilt): void
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

                $memory->merge($state, $live);
            } elseif ($memory->cache->get($state::class, $memory->cacheId($state)) === null) {
                $memory->cache->put($state);
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
