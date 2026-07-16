<?php

use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;
use Thunk\Verbs\Facades\Verbs;
use Thunk\Verbs\Lifecycle\Queue;
use Thunk\Verbs\State;
use Thunk\Verbs\State\Cache\InMemoryCache;
use Thunk\Verbs\State\ReconstitutingStateManager;
use Thunk\Verbs\State\StateManager;

/*
 * A state referenced by a queued-but-uncommitted event must not be evicted by a
 * prune mid-batch: reloading it as a fresh instance would discard the mutations
 * already applied in memory and fork its identity. The Broker pins each event's
 * states when it queues them and releases the pins once they're committed.
 */
it('keeps queued-but-uncommitted states pinned through a prune', function () {
    // Rebind the scope with a tiny capacity so a prune would otherwise evict.
    app()->instance(StateManager::class, new ReconstitutingStateManager(
        queue: app(Queue::class),
        cache: new InMemoryCache(capacity: 2),
    ));

    $ids = [snowflake_id(), snowflake_id(), snowflake_id()];

    // Fire three distinct-state events without committing.
    foreach ($ids as $id) {
        InFlightPinningTestEvent::fire(state_id: $id);
    }

    $scope = app(StateManager::class);

    // Simulate memory pressure mid-batch. Without pinning this would drop the
    // two oldest in-flight states (capacity is 2, three states are live).
    expect($scope->willPrune())->toBeTrue();
    $scope->prune();

    foreach ($ids as $id) {
        expect($scope->cache->has(InFlightPinningTestState::class, (string) $id))->toBeTrue();
    }

    // Committing still produces correct, durable results for every state.
    Verbs::commit();
    $scope->reset();

    foreach ($ids as $id) {
        expect(InFlightPinningTestState::load($id)->count)->toBe(1);
    }
});

/*
 * Pins are refcounts: an event fired from a handler mid-commit re-pins states
 * the outer batch already pinned, and releasing the outer batch's pins must
 * not expose the still-in-flight inner batch to eviction.
 */
it('keeps a state consistent through a nested commit batch under cache pressure', function () {
    app()->instance(StateManager::class, new ReconstitutingStateManager(
        queue: app(Queue::class),
        cache: new InMemoryCache(capacity: 1),
    ));

    $id = snowflake_id();

    NestedCommitPinEvent::fire(state_id: $id);

    $held = NestedCommitPinState::load($id);

    Verbs::commit();

    // The outer event and the handler-fired inner event both applied to the
    // same live instance, and the persisted snapshot agrees.
    expect($held->count)->toBe(2)
        ->and(NestedCommitPinState::load($id))->toBe($held);

    app(StateManager::class)->reset();

    expect(NestedCommitPinState::load($id)->count)->toBe(2);
});

class InFlightPinningTestState extends State
{
    public int $count = 0;
}

class InFlightPinningTestEvent extends Event
{
    #[StateId(InFlightPinningTestState::class)]
    public int $state_id;

    public function apply(InFlightPinningTestState $state): void
    {
        $state->count++;
    }
}

class NestedCommitPinState extends State
{
    public int $count = 0;
}

class NestedCommitFillerState extends State
{
    public int $noise = 0;
}

class NestedCommitPinEvent extends Event
{
    #[StateId(NestedCommitPinState::class)]
    public int $state_id;

    public function apply(NestedCommitPinState $state): void
    {
        $state->count++;
    }

    public function handle(): void
    {
        NestedCommitPinInnerEvent::fire(state_id: $this->state_id);

        // Load unrelated states to push the (capacity: 1) cache over its limit,
        // so the recursive commit's prune has real eviction pressure.
        NestedCommitFillerState::load(snowflake_id());
        NestedCommitFillerState::load(snowflake_id());
    }
}

class NestedCommitPinInnerEvent extends Event
{
    #[StateId(NestedCommitPinState::class)]
    public int $state_id;

    public function apply(NestedCommitPinState $state): void
    {
        $state->count++;
    }
}
