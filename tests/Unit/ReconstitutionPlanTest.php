<?php

use Illuminate\Support\Facades\DB;
use Thunk\Verbs\State;
use Thunk\Verbs\State\ReconstitutionPlan;
use Thunk\Verbs\State\StateIdentity;

function insertPlanPivot(int $event_id, int $state_id, string $state_type): void
{
    DB::table('verb_state_events')->insert([
        'id' => snowflake_id(),
        'event_id' => $event_id,
        'state_id' => $state_id,
        'state_type' => $state_type,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function insertPlanSnapshot(string $type, int $state_id, ?int $last_event_id): void
{
    DB::table('verb_snapshots')->insert([
        'id' => snowflake_id(),
        'state_id' => $state_id,
        'type' => $type,
        'data' => '{}',
        'last_event_id' => $last_event_id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function planState(string $type, int $id): State
{
    $state = (new ReflectionClass($type))->newInstanceWithoutConstructor();
    $state->id = $id;

    return $state;
}

function planFor(array $states, bool $use_snapshots = true): ReconstitutionPlan
{
    return ReconstitutionPlan::plan(collect($states), use_snapshots: $use_snapshots);
}

test('it finds the correct states and events for one state', function () {
    $matching_state_types = [
        ReconstitutionPlanTestState1::class,
        ReconstitutionPlanTestState2::class,
        ReconstitutionPlanTestState3::class,
    ];
    $matching_state_ids = [10, 11, 12, 13, 14];
    $matching_event_ids = [100, 101, 102, 103, 105];

    foreach ($matching_state_ids as $state_index => $matching_state_id) {
        foreach ($matching_event_ids as $matching_event_id) {
            insertPlanPivot($matching_event_id, $matching_state_id, $matching_state_types[$state_index % count($matching_state_types)]);
        }
    }

    $plan = planFor([planState(ReconstitutionPlanTestState1::class, 10)]);

    expect($plan->members)->toHaveCount(5)
        ->and($plan->window)->toHaveCount(5)
        ->and($plan->window->all())->toBe($matching_event_ids)
        ->and($plan->floor)->toBeNull()
        ->and($plan->seeded)->toBeFalse();

    $member_ids = $plan->members
        ->map(fn (StateIdentity $state) => $state->state_id)
        ->sort()
        ->values()
        ->all();

    expect($member_ids)->toBe($matching_state_ids);
});

test('it finds the correct states and events for multiple states', function () {
    $matching_state_types = [
        ReconstitutionPlanTestState1::class,
        ReconstitutionPlanTestState2::class,
        ReconstitutionPlanTestState3::class,
    ];
    $matching_state_ids = [10, 11, 12, 13, 14];
    $matching_event_ids = [100, 101, 102, 103, 105];

    foreach ($matching_state_ids as $state_index => $matching_state_id) {
        foreach ($matching_event_ids as $matching_event_id) {
            insertPlanPivot($matching_event_id, $matching_state_id, $matching_state_types[$state_index % count($matching_state_types)]);
        }
    }

    $plan = planFor([
        planState(ReconstitutionPlanTestState1::class, 10),
        planState(ReconstitutionPlanTestState2::class, 11),
    ]);

    expect($plan->members)->toHaveCount(5)
        ->and($plan->window)->toHaveCount(5);
});

test('aligned snapshots produce a seeded plan windowed after the floor', function () {
    // States 10 and 11 share window event 103; both snapshots sit at 102.
    insertPlanPivot(100, 10, ReconstitutionPlanTestState1::class);
    insertPlanPivot(101, 10, ReconstitutionPlanTestState1::class);
    insertPlanPivot(101, 11, ReconstitutionPlanTestState2::class);
    insertPlanPivot(102, 11, ReconstitutionPlanTestState2::class);
    insertPlanPivot(103, 10, ReconstitutionPlanTestState1::class);
    insertPlanPivot(103, 11, ReconstitutionPlanTestState2::class);
    insertPlanPivot(104, 11, ReconstitutionPlanTestState2::class);

    insertPlanSnapshot(ReconstitutionPlanTestState1::class, 10, 102);
    insertPlanSnapshot(ReconstitutionPlanTestState2::class, 11, 102);

    $plan = planFor([planState(ReconstitutionPlanTestState1::class, 10)]);

    expect($plan->seeded)->toBeTrue()
        ->and($plan->floor)->toBe(102)
        ->and($plan->window->all())->toBe([103, 104])
        ->and($plan->members)->toHaveCount(2);
});

test('states connected only through pre-floor events stay outside the component', function () {
    // Event 101 connects 10 and 11, but both snapshots absorbed it—so a load
    // of 10 replays only its own tail and never drags 11 into the rebuild.
    insertPlanPivot(100, 10, ReconstitutionPlanTestState1::class);
    insertPlanPivot(101, 10, ReconstitutionPlanTestState1::class);
    insertPlanPivot(101, 11, ReconstitutionPlanTestState2::class);
    insertPlanPivot(102, 11, ReconstitutionPlanTestState2::class);
    insertPlanPivot(103, 10, ReconstitutionPlanTestState1::class);

    insertPlanSnapshot(ReconstitutionPlanTestState1::class, 10, 101);
    insertPlanSnapshot(ReconstitutionPlanTestState2::class, 11, 102);

    $plan = planFor([planState(ReconstitutionPlanTestState1::class, 10)]);

    expect($plan->seeded)->toBeTrue()
        ->and($plan->floor)->toBe(101)
        ->and($plan->members)->toHaveCount(1)
        ->and($plan->window->all())->toBe([103]);
});

test('a member ahead of the floor routes the plan to a blank baseline', function () {
    insertPlanPivot(100, 10, ReconstitutionPlanTestState1::class);
    insertPlanPivot(101, 10, ReconstitutionPlanTestState1::class);
    insertPlanPivot(101, 11, ReconstitutionPlanTestState2::class);
    insertPlanPivot(102, 11, ReconstitutionPlanTestState2::class);
    insertPlanPivot(103, 10, ReconstitutionPlanTestState1::class);

    // State 10's snapshot is older, so the shared event 101 is inside the
    // window—and state 11 already absorbed it, so seeding cannot be exact.
    insertPlanSnapshot(ReconstitutionPlanTestState1::class, 10, 100);
    insertPlanSnapshot(ReconstitutionPlanTestState2::class, 11, 102);

    $plan = planFor([planState(ReconstitutionPlanTestState1::class, 10)]);

    expect($plan->seeded)->toBeFalse()
        ->and($plan->floor)->toBeNull()
        ->and($plan->window->all())->toBe([100, 101, 102, 103]);
});

test('a member with no snapshot routes the plan to a blank baseline', function () {
    insertPlanPivot(100, 10, ReconstitutionPlanTestState1::class);
    insertPlanPivot(101, 10, ReconstitutionPlanTestState1::class);
    insertPlanPivot(101, 11, ReconstitutionPlanTestState2::class);
    insertPlanPivot(102, 11, ReconstitutionPlanTestState2::class);
    insertPlanPivot(103, 10, ReconstitutionPlanTestState1::class);
    insertPlanPivot(103, 11, ReconstitutionPlanTestState2::class);

    insertPlanSnapshot(ReconstitutionPlanTestState1::class, 10, 101);

    $plan = planFor([planState(ReconstitutionPlanTestState1::class, 10)]);

    expect($plan->seeded)->toBeFalse()
        ->and($plan->floor)->toBeNull()
        ->and($plan->window->all())->toBe([100, 101, 102, 103]);
});

test('a newly discovered member drags the floor down and expands the window', function () {
    // State 10 sits at 103; event 104 connects it to state 11, whose snapshot
    // is much older (100)—so the window must expand back past 100.
    insertPlanPivot(100, 11, ReconstitutionPlanTestState2::class);
    insertPlanPivot(101, 11, ReconstitutionPlanTestState2::class);
    insertPlanPivot(102, 10, ReconstitutionPlanTestState1::class);
    insertPlanPivot(103, 10, ReconstitutionPlanTestState1::class);
    insertPlanPivot(104, 10, ReconstitutionPlanTestState1::class);
    insertPlanPivot(104, 11, ReconstitutionPlanTestState2::class);

    insertPlanSnapshot(ReconstitutionPlanTestState1::class, 10, 103);
    insertPlanSnapshot(ReconstitutionPlanTestState2::class, 11, 100);

    $plan = planFor([planState(ReconstitutionPlanTestState1::class, 10)]);

    // Discovery starts at floor 103 (only member: 10), finds event 104, which
    // adds member 11 at last_event_id 100 and drops the floor—re-querying every
    // member picks up 101, 102, and 103. The misaligned last-event-ids then route
    // to blank (state 10 absorbed 102/103, which are inside the window).
    expect($plan->members)->toHaveCount(2)
        ->and($plan->seeded)->toBeFalse()
        ->and($plan->window->all())->toBe([100, 101, 102, 103, 104]);
});

test('disabling snapshots forces a blank full-component plan', function () {
    insertPlanPivot(100, 10, ReconstitutionPlanTestState1::class);
    insertPlanPivot(101, 10, ReconstitutionPlanTestState1::class);

    insertPlanSnapshot(ReconstitutionPlanTestState1::class, 10, 100);

    $plan = planFor([planState(ReconstitutionPlanTestState1::class, 10)], use_snapshots: false);

    expect($plan->seeded)->toBeFalse()
        ->and($plan->floor)->toBeNull()
        ->and($plan->window->all())->toBe([100, 101]);
});

class ReconstitutionPlanTestState1 extends State {}
class ReconstitutionPlanTestState2 extends State {}
class ReconstitutionPlanTestState3 extends State {}
