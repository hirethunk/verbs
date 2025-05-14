<?php

use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Facades\Verbs;
use Thunk\Verbs\Lifecycle\StateManager;
use Thunk\Verbs\Models\VerbSnapshot;
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
 *
 * ALTERNATE TEST?:
 *
 * - LeftState and RightState
 * - IncrementLeftByRight and IncrementRightByLeft
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
    StateReconstitutionTestEvent1::fire(state1_id: $state1_id, state2_id: $state2_id);

    expect($state1->counter)->toBe(2)
        ->and($state2->counter)->toBe(3);

    Verbs::commit();
    app(StateManager::class)->reset(include_storage: true);

    $state1 = StateReconstitutionTestState1::load($state1_id);
    $state2 = StateReconstitutionTestState2::load($state2_id);

    expect($state1->counter)->toBe(2)
        ->and($state2->counter)->toBe(3);
});

test('partially up-to-date snapshots', function () {
    // event 2 increments state 2
    // event 1 adds state 2 + state 1, then increments state 2

    $event1 = StateReconstitutionTestEvent2::fire(state2_id: 2);               // 1=0, 2=1
    $event2 = StateReconstitutionTestEvent2::fire(state2_id: 2);               // 1=0, 2=2
    $event3 = StateReconstitutionTestEvent1::fire(state1_id: 1, state2_id: 2); // 1=2, 2=3
    $event4 = StateReconstitutionTestEvent2::fire(state2_id: 2);               // 1=2, 2=4
    $event5 = StateReconstitutionTestEvent1::fire(state1_id: 1, state2_id: 2); // 1=6, 2=5

    dump([$event1->id, $event2->id, $event3->id, $event4->id, $event5->id]);

    Verbs::commit();

    $state1 = StateReconstitutionTestState1::load(1);
    $state2 = StateReconstitutionTestState2::load(2);

    expect($state1->counter)->toBe(6)
        ->and($state2->counter)->toBe(5);

    // Reset the snapshots to what they looked like at event 3

    $snapshot1 = VerbSnapshot::query()->where('state_id', 1)->sole();
    $snapshot1->update([
        'data' => '{"counter":2}',
        'last_event_id' => $event3->id,
    ]);

    $snapshot2 = VerbSnapshot::query()->where('state_id', 2)->sole();
    $snapshot2->update([
        'data' => '{"counter":3}',
        'last_event_id' => $event3->id,
    ]);

    app(StateManager::class)->reset();

    $state1 = StateReconstitutionTestState1::load(1);
    $state2 = StateReconstitutionTestState2::load(2);

    dump($state1);
    dump(VerbSnapshot::all()->toArray());

    expect($state1->counter)->toBe(6);
    expect($state2->counter)->toBe(5);
});

test('partially deleted snapshots', function () {
    StateReconstitutionTestEvent2::fire(state2_id: 2);                 // 1=null, 2=1
    StateReconstitutionTestEvent2::fire(state2_id: 2);                 // 1=null, 2=2
    StateReconstitutionTestEvent1::fire(state1_id: 1, state2_id: 2);   // 1=2, 2=3
    StateReconstitutionTestEvent2::fire(state2_id: 2);                 // 1=2, 2=4
    StateReconstitutionTestEvent1::fire(state1_id: 1, state2_id: 2);   // 1=6, 2=5

    Verbs::commit();

    $state1 = StateReconstitutionTestState1::load(1);
    $state2 = StateReconstitutionTestState2::load(2);

    expect($state1->counter)->toBe(6)
        ->and($state2->counter)->toBe(5);

    VerbSnapshot::query()->where('state_id', 1)->delete();

    app(StateManager::class)->reset();

    $state1 = StateReconstitutionTestState1::load(1);
    $state2 = StateReconstitutionTestState2::load(2);

    expect($state1->counter)->toBe(6);
    expect($state2->counter)->toBe(5);
});

test('partially up-to-date, but out of sync snapshots', function () {
    StateReconstitutionTestEvent2::fire(state2_id: 2);                         // 1=null, 2=1
    $event2 = StateReconstitutionTestEvent2::fire(state2_id: 2);               // 1=null, 2=2
    $event3 = StateReconstitutionTestEvent1::fire(state1_id: 1, state2_id: 2); // 1=2, 2=3
    StateReconstitutionTestEvent2::fire(state2_id: 2);                         // 1=2, 2=4
    StateReconstitutionTestEvent1::fire(state1_id: 1, state2_id: 2);           // 1=6, 2=5

    Verbs::commit();

    $state1 = StateReconstitutionTestState1::load(1);
    $state2 = StateReconstitutionTestState2::load(2);

    expect($state1->counter)->toBe(6)
        ->and($state2->counter)->toBe(5);

    $snapshot1 = VerbSnapshot::query()->where('state_id', 1)->sole();
    $snapshot1->update([
        'data' => '{"counter":2}',
        'last_event_id' => $event3->id,
    ]);

    $snapshot2 = VerbSnapshot::query()->where('state_id', 2)->sole();
    $snapshot2->update([
        'data' => '{"counter":2}', // FIXME: This maybe can't happen?
        'last_event_id' => $event2->id,
    ]);

    app(StateManager::class)->reset();

    // dump('---- RESET ----');

    $state1 = StateReconstitutionTestState1::load(1);
    $state2 = StateReconstitutionTestState2::load(2);

    dump(app(StateManager::class));

    expect($state1->counter)->toBe(6);
    expect($state2->counter)->toBe(5);
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
        dump("[event 1] incrementing \$state1->counter from {$state1->counter} to ({$state1->counter} + {$state2->counter})");
        $state1->counter = $state1->counter + $state2->counter;
        dump("[event 1] incrementing \$state2->counter from {$state2->counter} to \$state2->counter++");
        $state2->counter++;
    }
}

class StateReconstitutionTestEvent2 extends \Thunk\Verbs\Event
{
    #[StateId(StateReconstitutionTestState2::class)]
    public int $state2_id;

    public function apply(StateReconstitutionTestState2 $state2): void
    {
        dump("[event 2] incrementing \$state2->counter from {$state2->counter} to \$state2->counter++");
        $state2->counter++;
    }
}
