<?php

use Illuminate\Support\Facades\DB;
use Thunk\Verbs\Contracts\StoresEvents;
use Thunk\Verbs\Contracts\StoresSnapshots;
use Thunk\Verbs\Event;
use Thunk\Verbs\SingletonState;
use Thunk\Verbs\State;
use Thunk\Verbs\State\StateIdentity;

/*
 * Reconstitution reads (staleness, probe, discovery, snapshot last-event-ids) all
 * go through the StoresEvents/StoresSnapshots contracts, so these tests pin
 * the default stores' semantics directly—especially the singleton nuance:
 * a singleton is matched by type alone, and its events may be recorded under
 * multiple incidental state_id rows that must aggregate to one last event id.
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

test('singleton last-event-ids aggregate across incidental state_id rows', function () {
    $e1 = insertContractReadsEvent(ContractReadsSingletonState::class, snowflake_id());
    $e2 = insertContractReadsEvent(ContractReadsSingletonState::class, snowflake_id());

    $events = app(StoresEvents::class);

    // The state_id passed for a singleton is incidental and must not constrain
    // the lookup: e2 lives under a different row but still counts as "beyond."
    expect($events->hasUnappliedEvents([
        new StateIdentity(ContractReadsSingletonState::class, snowflake_id(), last_event_id: $e1),
    ]))->toBeTrue();

    expect($events->hasUnappliedEvents([
        new StateIdentity(ContractReadsSingletonState::class, snowflake_id(), last_event_id: $e2),
    ]))->toBeFalse();

    // The absorbed-window probe must see events under every incidental row too.
    expect($events->hasAppliedEventsAfter([
        new StateIdentity(ContractReadsSingletonState::class, snowflake_id(), last_event_id: $e2),
    ], after_id: $e1))->toBeTrue();
});

test('keyed states never match rows for other ids', function () {
    $a = snowflake_id();
    $b = snowflake_id();

    $e1 = insertContractReadsEvent(ContractReadsState::class, $a);
    insertContractReadsEvent(ContractReadsState::class, $b);

    $events = app(StoresEvents::class);

    expect($events->hasUnappliedEvents([
        new StateIdentity(ContractReadsState::class, $a, last_event_id: $e1),
    ]))->toBeFalse();

    expect($events->hasUnappliedEvents([
        new StateIdentity(ContractReadsState::class, $a),
    ]))->toBeTrue();
});

test('the window probe only matches events inside (floor, last_event_id]', function () {
    $a = snowflake_id();

    $e1 = insertContractReadsEvent(ContractReadsState::class, $a);
    $e2 = insertContractReadsEvent(ContractReadsState::class, $a);
    insertContractReadsEvent(ContractReadsState::class, $a);

    $events = app(StoresEvents::class);

    expect($events->hasAppliedEventsAfter([
        new StateIdentity(ContractReadsState::class, $a, last_event_id: $e2),
    ], after_id: $e1))->toBeTrue();

    expect($events->hasAppliedEventsAfter([
        new StateIdentity(ContractReadsState::class, $a, last_event_id: $e2),
    ], after_id: $e2))->toBeFalse();

    // No last_event_id means nothing was absorbed, no matter what rows exist.
    expect($events->hasAppliedEventsAfter([
        new StateIdentity(ContractReadsState::class, $a),
    ], after_id: $e1))->toBeFalse();
});

test('discovery reads return distinct event ids and state identities', function () {
    $a = snowflake_id();
    $b = snowflake_id();

    $e1 = insertContractReadsEvent(ContractReadsState::class, $a);
    $e2 = insertContractReadsEvent(ContractReadsState::class, $a);
    insertContractReadsPivot($e2, ContractReadsState::class, $b);

    $events = app(StoresEvents::class);

    // The contract promises distinct ids, not ordered ones (the plan sorts its
    // own window), so sort before asserting—DISTINCT return order is driver-specific.
    expect($events->eventIdsFor([new StateIdentity(ContractReadsState::class, $a)])->sort()->values()->all())
        ->toBe([$e1, $e2])
        ->and($events->eventIdsFor([new StateIdentity(ContractReadsState::class, $a)], after_id: $e1)->all())
        ->toBe([$e2]);

    $identities = $events->stateIdentitiesFor([$e1, $e2]);

    expect($identities->map(fn (StateIdentity $state) => $state->state_type.':'.$state->state_id)->sort()->values()->all())
        ->toBe(collect([$a, $b])->map(fn ($id) => ContractReadsState::class.':'.$id)->sort()->values()->all());
});

test('snapshot last-event-ids match singletons by type and normalize to native ids', function () {
    $a = snowflake_id();
    $keyed_last_event_id = snowflake_id();
    $singleton_last_event_id = snowflake_id();

    insertContractReadsSnapshot(ContractReadsState::class, $a, $keyed_last_event_id);
    insertContractReadsSnapshot(ContractReadsSingletonState::class, 0, $singleton_last_event_id);

    $found = app(StoresSnapshots::class)->hydrateLastEventIds([
        new StateIdentity(ContractReadsState::class, $a),
        new StateIdentity(ContractReadsSingletonState::class, snowflake_id()),
        new StateIdentity(ContractReadsState::class, snowflake_id()),
    ]);

    expect($found)->toHaveCount(2)
        ->and($found->firstWhere('state_type', ContractReadsState::class)->last_event_id)->toBe($keyed_last_event_id)
        ->and($found->firstWhere('state_type', ContractReadsSingletonState::class)->last_event_id)->toBe($singleton_last_event_id);
});

class ContractReadsState extends State {}

class ContractReadsSingletonState extends SingletonState {}

class ContractReadsEvent extends Event {}
