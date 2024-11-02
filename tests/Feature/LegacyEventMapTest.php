<?php

use Thunk\Verbs\Event;
use Thunk\Verbs\Facades\Verbs;

it('can map legacy events to new events as an array', function () {
    Verbs::mapLegacyEvents([
        NewEvent::class => LegacyEvent::class,
    ]);

    LegacyEvent::fire(name: 'test');

    Verbs::commit();

    $events = app(\Thunk\Verbs\Lifecycle\EventStore::class)->read();

    $this->assertInstanceOf(NewEvent::class, $events->first());
});

it('can map legacy events to new events as a nested array', function () {
    Verbs::mapLegacyEvents([
        NewEvent::class => [LegacyEvent::class],
    ]);

    LegacyEvent::fire(name: 'test');

    Verbs::commit();

    $events = app(\Thunk\Verbs\Lifecycle\EventStore::class)->read();

    $this->assertInstanceOf(NewEvent::class, $events->first());
});

it('throws a helpful exception when an event is not found in the map', function () {
    NewEvent::fire(name: 'test');

    Verbs::commit();

    $event = \Thunk\Verbs\Models\VerbEvent::first();
    $event->type = 'non-existent-event';
    $event->save();

    $this->expectException(\Thunk\Verbs\Exceptions\UnableToReadEventException::class);

    $events = app(\Thunk\Verbs\Lifecycle\EventStore::class)->read();
});

class LegacyEvent extends Event
{
    public function __construct(
        public string $name,
    ) {}
}

class NewEvent extends Event
{
    public function __construct(
        public string $name,
    ) {}
}
