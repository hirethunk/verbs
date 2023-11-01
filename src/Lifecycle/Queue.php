<?php

namespace Thunk\Verbs\Lifecycle;

use Thunk\Verbs\Event;

class Queue
{
    public array $event_queue = [];

    public function queue(Event $event)
    {
        $this->event_queue[] = $event;

        dump('queue', $this->event_queue);
    }

    public function flush(): array
    {
        $events = $this->event_queue;

        dump('flush', $this->event_queue);

        if (! app(EventStore::class)->write($events)) {
            throw new \Exception('Failed to write events to store.');
        }

        $this->event_queue = [];

        return $events;
    }

    public function getEvents(): array
    {
        return $this->event_queue;
    }
}
