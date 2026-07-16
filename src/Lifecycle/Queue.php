<?php

namespace Thunk\Verbs\Lifecycle;

use Thunk\Verbs\Contracts\StoresEvents;
use Thunk\Verbs\Event;
use Thunk\Verbs\Exceptions\UnableToStoreEventsException;

class Queue
{
    public array $event_queue = [];

    public function queue(Event $event)
    {
        $this->event_queue[] = $event;
    }

    public function flush(): array
    {
        $events = $this->event_queue;

        // Concurrency is guarded at the storage layer: EventStore::write() runs
        // guardAgainstConcurrentWrites() against the persisted max event id per
        // state before inserting, throwing a ConcurrencyException on a conflict.
        if (! app(StoresEvents::class)->write($events)) {
            throw new UnableToStoreEventsException($events);
        }

        $this->event_queue = [];

        return $events;
    }

    public function getEvents(): array
    {
        return $this->event_queue;
    }

    /** @param  Event[]  $events */
    public function restore(array $events): void
    {
        $this->event_queue = array_merge($events, $this->event_queue);
    }
}
