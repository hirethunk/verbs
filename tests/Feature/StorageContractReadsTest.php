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

// MySQL's default collation matches string ids case-insensitively, so the SQL
// hands back rows the PHP-side split then has to route to an identity. If that
// split is stricter than the database was, the row is dropped and a stale state
// is reported as up to date—the one failure this whole read exists to prevent.
test('keyed states keep rows the database matched case-insensitively', function () {
    DB::statement('drop table verb_state_events');
    DB::statement('create table verb_state_events (
        id integer primary key,
        event_id integer not null,
        state_id text collate nocase not null,
        state_type varchar not null,
        created_at datetime,
        updated_at datetime
    )');

    $event_id = snowflake_id();

    DB::table('verb_state_events')->insert([
        'id' => snowflake_id(),
        'event_id' => $event_id,
        'state_id' => 'ABC',
        'state_type' => ContractReadsState::class,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $events = app(StoresEvents::class);

    expect($events->hasUnappliedEvents([
        new StateIdentity(ContractReadsState::class, 'abc'),
    ]))->toBeTrue();

    expect($events->hasUnappliedEvents([
        new StateIdentity(ContractReadsState::class, 'abc', last_event_id: $event_id),
    ]))->toBeFalse();
})->skip(
    fn () => DB::connection()->getDriverName() !== 'sqlite',
    'Needs a case-insensitive id column, which only SQLite can be given here.',
);

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

// "After id 0" is a real request—it means "everything above the very first
// possible id"—so it has to be distinguished from "no ceiling given" rather
// than lumped in with it by a truthiness check.
test('reading after an id of zero excludes the event at zero', function () {
    $state_id = snowflake_id();
    $second_id = snowflake_id();

    insertContractReadsEventWithId(0, ContractReadsState::class, $state_id);
    insertContractReadsEventWithId($second_id, ContractReadsState::class, $state_id);

    $events = app(StoresEvents::class);
    $state = ContractReadsState::load($state_id);

    expect($events->read(after_id: 0)->map(fn (Event $event) => $event->id)->all())
        ->toBe([$second_id])
        ->and($events->read(state: $state, after_id: 0)->map(fn (Event $event) => $event->id)->all())
        ->toBe([$second_id]);
});

function insertContractReadsEventWithId(int $event_id, string $state_type, int $state_id): void
{
    DB::table('verb_events')->insert([
        'id' => $event_id,
        'type' => ContractReadsEvent::class,
        'data' => '{}',
        'metadata' => '{}',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    insertContractReadsPivot($event_id, $state_type, $state_id);
}

class ContractReadsState extends State {}

class ContractReadsSingletonState extends SingletonState {}

class ContractReadsEvent extends Event {}
