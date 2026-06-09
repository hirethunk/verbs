<?php

use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;
use Thunk\Verbs\Facades\Verbs;
use Thunk\Verbs\State;
use Thunk\Verbs\State\StateManager;

/*
 * During a long replay the cache is pruned to bound memory. A state that was
 * evicted (after its snapshot was written) must reload from that snapshot when a
 * later event touches it again—reloading it as a blank state would silently
 * discard everything applied before the prune. This pins that the "replaying"
 * load path still hydrates from snapshots even though it skips reconstitution.
 */
it('reloads an evicted state from its snapshot during replay', function () {
    $id = snowflake_id();

    ReplayEvictionTestEvent::fire(state_id: $id); // count => 1
    Verbs::commit();                              // snapshot written

    $scope = app(StateManager::class);
    $scope->reset();            // simulate the state being evicted from the cache
    $scope->setReplaying(true); // simulate being mid-replay

    try {
        $state = ReplayEvictionTestState::load($id);

        // Without snapshot hydration on the replay path this would be a blank state.
        expect($state->count)->toBe(1);
    } finally {
        $scope->setReplaying(false);
    }
});

class ReplayEvictionTestState extends State
{
    public int $count = 0;
}

class ReplayEvictionTestEvent extends Event
{
    #[StateId(ReplayEvictionTestState::class)]
    public int $state_id;

    public function apply(ReplayEvictionTestState $state): void
    {
        $state->count++;
    }
}
