<?php

use Thunk\Verbs\Event;
use Thunk\Verbs\Facades\Verbs;

it('can store and restore an event with and without alias', function () {
    Event::eventMap([
        'with-alias' => EventMapEventWithAlias::class,
    ]);

    EventMapEventWithAlias::fire();
    EventMapEvent::fire();

    Verbs::commit();

    [$eventWithAlias, $eventWithoutAlias] = \Thunk\Verbs\Models\VerbEvent::all();

    expect($eventWithAlias)
        ->type->toBe('with-alias')
        ->event()->toBeInstanceOf(EventMapEventWithAlias::class);

    expect($eventWithoutAlias)
        ->type->toBe(EventMapEvent::class)
        ->event()->toBeInstanceOf(EventMapEvent::class);
});

test('using an event without an entry in the event map throws an exception when event map is required', function () {
    Event::eventMap([
        'with-alias' => EventMapEventWithAlias::class,
    ]);
    EventMapEvent::requireEventMap();

    EventMapEvent::fire();

    Verbs::commit();
})
    ->throws(\Thunk\Verbs\Exceptions\EventMapViolationException::class, 'No alias defined for event [EventMapEvent].')
    ->after(fn () => EventMapEvent::requireEventMap(false));

class EventMapEventWithAlias extends Event {}

class EventMapEvent extends Event {}
