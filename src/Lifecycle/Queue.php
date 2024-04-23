<?php

namespace Thunk\Verbs\Lifecycle;

use Thunk\Verbs\Contracts\StoresEvents;
use Thunk\Verbs\Event;
use Thunk\Verbs\Exceptions\UnableToStoreEventsException;

class Queue
{
    public array $event_queue = [];

    public function __construct(
        public StoresEvents $event_store,
    ){}

    public function queue(Event $event)
    {
        $this->event_queue[] = $event;
    }

    public function flush(): array
    {
        $events = $this->event_queue;

        // TODO: Concurrency check

        if (! $this->event_store->write($events)) {
            throw new UnableToStoreEventsException($events);
        }

        $this->event_queue = [];

        return $events;
    }

    public function getEvents(): array
    {
        return $this->event_queue;
    }
}
