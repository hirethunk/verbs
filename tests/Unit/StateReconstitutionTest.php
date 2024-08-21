<?php

use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Facades\Verbs;
use Thunk\Verbs\Lifecycle\StateManager;
use Thunk\Verbs\State;

/*
 * The Problem(s)
 *
 * FIRST PROBLEM:
 * - We try to load state1, but we don't have an up-to-date snapshot
 * - StateManager::load tries to reconstitute state from events
 * - One of those Event::apply methods load state2
 * - Best case scenario: we reconstitute state2 before continuing
 * - Worst case scenario: reconstituting state2 tries to reconstitute state1, and we're in an infinite loop
 * - (if no loop) state1 continues to reconstitute, but it's acting with state2 FULLY up-to-date, not
 *   just up-to-date with where state1 happens to be
 *
 * TO TEST FIRST PROBLEM:
 * - Event1 adds State1::counter to State2::counter and increments State2::counter
 * - Event2 increments State2::counter
 *
 * SECOND PROBLEM:
 * - We try to load state1, but we don't have an up-to-date snapshot
 * - StateManager::load tries to reconstitute state from events
 * - One of those Event::apply methods requires state1 and state2, so we need to load state2
 * - Reconstituting state2 re-runs the same apply method on state2 before also running it on state1
 * - Double-apply happens
 */

// FIXME: We need to account for partially up-to-date snapshots that only need *some* events applied but not all

test('scenario 1', function () {
    $state1_id = snowflake_id();
    $state2_id = snowflake_id();

    StateReconstitutionTestEvent1::fire(state1_id: $state1_id, state2_id: $state2_id);

    $state1 = StateReconstitutionTestState1::load($state1_id);
    $state2 = StateReconstitutionTestState2::load($state2_id);

    expect($state1->counter)->toBe(0)
        ->and($state2->counter)->toBe(1);

    StateReconstitutionTestEvent2::fire(state2_id: $state2_id);

    expect($state1->counter)->toBe(0)
        ->and($state2->counter)->toBe(2);

    Verbs::commit();
    app(StateManager::class)->reset(include_storage: true);

    $state1 = StateReconstitutionTestState1::load($state1_id);
    $state2 = StateReconstitutionTestState2::load($state2_id);

    expect($state1->counter)->toBe(0)
        ->and($state2->counter)->toBe(2);
});

class StateReconstitutionTestState1 extends State
{
    public int $counter = 0;
}

class StateReconstitutionTestState2 extends State
{
    public int $counter = 0;
}

class StateReconstitutionTestEvent1 extends \Thunk\Verbs\Event
{
    #[StateId(StateReconstitutionTestState1::class)]
    public int $state1_id;

    #[StateId(StateReconstitutionTestState2::class)]
    public int $state2_id;

    public function apply(StateReconstitutionTestState1 $state1, StateReconstitutionTestState2 $state2): void
    {
        dump("Applying event {$this->id}");
        $state1->counter = $state1->counter + $state2->counter;
        $state2->counter++;
    }
}

class StateReconstitutionTestEvent2 extends \Thunk\Verbs\Event
{
    #[StateId(StateReconstitutionTestState2::class)]
    public int $state2_id;

    public function apply(StateReconstitutionTestState2 $state2): void
    {
        dump("Applying event {$this->id}");
        $state2->counter++;
    }
}
