<?php

use Thunk\Verbs\Event;
use Thunk\Verbs\Exceptions\ConcurrencyException;
use Thunk\Verbs\Lifecycle\EventStore;
use Thunk\Verbs\Models\VerbEvent;
use Thunk\Verbs\State;
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

class ConcurrencyTestEvent extends Event
{
    public function states(): StateCollection
    {
        return StateCollection::make([ConcurrencyTestState::singleton()]);
    }
}

class ConcurrencyTestState extends State {}
