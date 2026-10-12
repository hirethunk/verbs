<?php

namespace Thunk\Verbs\State;

use Illuminate\Support\Collection;
use Thunk\Verbs\Contracts\StoresSnapshots;
use Thunk\Verbs\State;

/**
 * hydrate-on-miss: snapshot · advance-on-stale: no
 *
 * The policy for an explicit replay. The Broker feeds every event in order,
 * so nothing may advance on its own—reconstituting mid-stream would
 * double-apply events—but a state evicted mid-replay (after its snapshot was
 * written) must reload from that snapshot, not as a blank, or everything
 * applied before the prune would be silently discarded.
 *
 * Replay forbids queued events (see Broker::replay()), so the uncommitted-
 * work answer is constant here: nothing ever needs protecting.
 */
class ReplayResolver implements ReappliesHistory, StateResolver
{
    use HydratesFromSnapshots;

    public function __construct(
        protected StoresSnapshots $snapshots,
    ) {}

    public function hasUncommittedEvents(State $state): bool
    {
        return false;
    }

    public function reconcile(StateManager $memory, Collection $states): void
    {
        //
    }
}
