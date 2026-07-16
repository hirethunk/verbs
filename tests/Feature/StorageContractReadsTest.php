<?php

use Illuminate\Support\Facades\DB;
use Thunk\Verbs\Contracts\StoresEvents;
use Thunk\Verbs\Contracts\StoresSnapshots;
use Thunk\Verbs\Event;
use Thunk\Verbs\SingletonState;
use Thunk\Verbs\State;
use Thunk\Verbs\State\StateIdentity;

/*
 * Reconstitution reads (staleness, probe, discovery, snapshot positions) all
 * go through the StoresEvents/StoresSnapshots contracts, so these tests pin
 * the default stores' semantics directly—especially the singleton nuance:
 * a singleton is matched by type alone, and its events may be recorded under
 * multiple incidental state_id rows that must aggregate to one position.
 */

function insertContractReadsEvent(string $state_type, int $state_id): int
{
    $event_id = snowflake_id();

    DB::table('verb_events')->insert([
        'id' => $event_id,
        'type' => ContractReadsEvent::class,
        'data' => '{}',
        'metadata' => '{}',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    insertContractReadsPivot($event_id, $state_type, $state_id);

    return $event_id;
}

function insertContractReadsPivot(int $event_id, string $state_type, int $state_id): void
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

function insertContractReadsSnapshot(string $type, int $state_id, int $last_event_id): void
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

test('singleton positions aggregate across incidental state_id rows', function () {
    $e1 = insertContractReadsEvent(ContractReadsSingletonState::class, snowflake_id());
    $e2 = insertContractReadsEvent(ContractReadsSingletonState::class, snowflake_id());

    $events = app(StoresEvents::class);

    // The state_id passed for a singleton is incidental and must not constrain
    // the lookup: e2 lives under a different row but still counts as "beyond."
    expect($events->hasEventsBeyondPositions([
        new StateIdentity(ContractReadsSingletonState::class, snowflake_id(), position: $e1),
    ]))->toBeTrue();

    expect($events->hasEventsBeyondPositions([
        new StateIdentity(ContractReadsSingletonState::class, snowflake_id(), position: $e2),
    ]))->toBeFalse();

    // The absorbed-window probe must see events under every incidental row too.
    expect($events->hasEventsWithinPositions([
        new StateIdentity(ContractReadsSingletonState::class, snowflake_id(), position: $e2),
    ], after: $e1))->toBeTrue();
});

test('keyed states never match rows for other ids', function () {
    $a = snowflake_id();
    $b = snowflake_id();

    $e1 = insertContractReadsEvent(ContractReadsState::class, $a);
    insertContractReadsEvent(ContractReadsState::class, $b);

    $events = app(StoresEvents::class);

    expect($events->hasEventsBeyondPositions([
        new StateIdentity(ContractReadsState::class, $a, position: $e1),
    ]))->toBeFalse();

    expect($events->hasEventsBeyondPositions([
        new StateIdentity(ContractReadsState::class, $a),
    ]))->toBeTrue();
});

test('the window probe only matches events inside (floor, position]', function () {
    $a = snowflake_id();

    $e1 = insertContractReadsEvent(ContractReadsState::class, $a);
    $e2 = insertContractReadsEvent(ContractReadsState::class, $a);
    insertContractReadsEvent(ContractReadsState::class, $a);

    $events = app(StoresEvents::class);

    expect($events->hasEventsWithinPositions([
        new StateIdentity(ContractReadsState::class, $a, position: $e2),
    ], after: $e1))->toBeTrue();

    expect($events->hasEventsWithinPositions([
        new StateIdentity(ContractReadsState::class, $a, position: $e2),
    ], after: $e2))->toBeFalse();

    // No position means nothing was absorbed, no matter what rows exist.
    expect($events->hasEventsWithinPositions([
        new StateIdentity(ContractReadsState::class, $a),
    ], after: $e1))->toBeFalse();
});

test('discovery reads return distinct event ids and state identities', function () {
    $a = snowflake_id();
    $b = snowflake_id();

    $e1 = insertContractReadsEvent(ContractReadsState::class, $a);
    $e2 = insertContractReadsEvent(ContractReadsState::class, $a);
    insertContractReadsPivot($e2, ContractReadsState::class, $b);

    $events = app(StoresEvents::class);

    expect($events->eventIdsForStates([new StateIdentity(ContractReadsState::class, $a)])->all())
        ->toBe([$e1, $e2])
        ->and($events->eventIdsForStates([new StateIdentity(ContractReadsState::class, $a)], after: $e1)->all())
        ->toBe([$e2]);

    $identities = $events->statesForEvents([$e1, $e2]);

    expect($identities->map(fn (StateIdentity $state) => $state->state_type.':'.$state->state_id)->sort()->values()->all())
        ->toBe(collect([$a, $b])->map(fn ($id) => ContractReadsState::class.':'.$id)->sort()->values()->all());
});

test('snapshot positions match singletons by type and normalize to native ids', function () {
    $a = snowflake_id();
    $keyed_position = snowflake_id();
    $singleton_position = snowflake_id();

    insertContractReadsSnapshot(ContractReadsState::class, $a, $keyed_position);
    insertContractReadsSnapshot(ContractReadsSingletonState::class, 0, $singleton_position);

    $positions = app(StoresSnapshots::class)->positions([
        new StateIdentity(ContractReadsState::class, $a),
        new StateIdentity(ContractReadsSingletonState::class, snowflake_id()),
        new StateIdentity(ContractReadsState::class, snowflake_id()),
    ]);

    expect($positions)->toHaveCount(2)
        ->and($positions->firstWhere('state_type', ContractReadsState::class)->position)->toBe($keyed_position)
        ->and($positions->firstWhere('state_type', ContractReadsSingletonState::class)->position)->toBe($singleton_position);
});

class ContractReadsState extends State {}

class ContractReadsSingletonState extends SingletonState {}

class ContractReadsEvent extends Event {}
