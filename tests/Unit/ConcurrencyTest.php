<?php

use Thunk\Verbs\Event;
use Thunk\Verbs\Exceptions\ConcurrencyException;
use Thunk\Verbs\Lifecycle\EventStore;
use Thunk\Verbs\Models\VerbEvent;
use Thunk\Verbs\Models\VerbStateEvent;
use Thunk\Verbs\SingletonState;
use Thunk\Verbs\Support\StateCollection;

it('does not throw on sequential events', function () {
    $store = app(EventStore::class);

    $event = new ConcurrencyTestEvent;
    $event->id = 1;
    ConcurrencyTestState::singleton()->last_event_id = 1;

    $store->write([$event]);

    $event2 = new ConcurrencyTestEvent;
    $event2->id = 2;
    ConcurrencyTestState::singleton()->last_event_id = 2;

    $store->write([$event2]);

    expect(VerbEvent::count())->toBe(2);
});

it('throws on non-sequential events', function () {
    $store = app(EventStore::class);

    $event = new ConcurrencyTestEvent;
    $event->id = 2;
    ConcurrencyTestState::singleton()->last_event_id = 2;

    $store->write([$event]);

    $event2 = new ConcurrencyTestEvent;
    $event2->id = 1;
    ConcurrencyTestState::singleton()->last_event_id = 1;

    $store->write([$event2]);
})->throws(ConcurrencyException::class);

// Event ids are snowflake ints today, but the guard's event-id comparison
// must already be safe for the planned move to string ids (UUIDv7): drivers
// hand id columns back as strings, and an int cast would reduce every ULID
// to its leading digits.
it('does not throw on sequential events with string event ids', function () {
    $store = app(EventStore::class);
    $state = ConcurrencyUlidTestState::singleton();

    seedUlidPivots($state);
    $state->last_event_id = '01ARZ3NDEKTSV4RRFFQ69G5FAB';

    $event = new ConcurrencyUlidTestEvent;
    $event->id = snowflake_id();

    $store->write([$event]);

    expect(VerbEvent::count())->toBe(1);
});

it('throws on non-sequential events with string event ids', function () {
    $store = app(EventStore::class);
    $state = ConcurrencyUlidTestState::singleton();

    seedUlidPivots($state);
    $state->last_event_id = '01ARZ3NDEKTSV4RRFFQ69G5FAA';

    $event = new ConcurrencyUlidTestEvent;
    $event->id = snowflake_id();

    $store->write([$event]);
})->throws(ConcurrencyException::class);

function seedUlidPivots(ConcurrencyUlidTestState $state): void
{
    VerbStateEvent::insert([
        [
            'id' => snowflake_id(),
            'event_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAA',
            'state_id' => $state->id,
            'state_type' => $state::class,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'id' => snowflake_id(),
            'event_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAB',
            'state_id' => $state->id,
            'state_type' => $state::class,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);
}

// A conflicting writer in another process records its pivot rows under *its*
// singleton instance's incidental id, so the guard must match singletons by
// type alone or it goes blind to every cross-process singleton conflict.
it('throws when a singleton conflict was written under a different incidental id', function () {
    $store = app(EventStore::class);
    $state = ConcurrencyForeignIdTestState::singleton();

    $state->last_event_id = snowflake_id();
    $foreign_event_id = snowflake_id();

    VerbStateEvent::insert([
        'id' => snowflake_id(),
        'event_id' => $foreign_event_id,
        'state_id' => snowflake_id(),
        'state_type' => $state::class,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $event = new ConcurrencyForeignIdTestEvent;
    $event->id = snowflake_id();

    $store->write([$event]);
})->throws(ConcurrencyException::class);

it('does not throw when a singleton has absorbed rows under other incidental ids', function () {
    $store = app(EventStore::class);
    $state = ConcurrencyForeignIdTestState::singleton();

    $absorbed_event_id = snowflake_id();

    VerbStateEvent::insert([
        'id' => snowflake_id(),
        'event_id' => $absorbed_event_id,
        'state_id' => snowflake_id(),
        'state_type' => $state::class,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $state->last_event_id = $absorbed_event_id;

    $event = new ConcurrencyForeignIdTestEvent;
    $event->id = snowflake_id();

    $store->write([$event]);

    expect(VerbEvent::count())->toBe(1);
});

class ConcurrencyTestEvent extends Event
{
    public function states(): StateCollection
    {
        return StateCollection::make([ConcurrencyTestState::singleton()]);
    }
}

class ConcurrencyTestState extends SingletonState {}

class ConcurrencyUlidTestEvent extends Event
{
    public function states(): StateCollection
    {
        return StateCollection::make([ConcurrencyUlidTestState::singleton()]);
    }
}

class ConcurrencyUlidTestState extends SingletonState {}

class ConcurrencyForeignIdTestEvent extends Event
{
    public function states(): StateCollection
    {
        return StateCollection::make([ConcurrencyForeignIdTestState::singleton()]);
    }
}

class ConcurrencyForeignIdTestState extends SingletonState {}
