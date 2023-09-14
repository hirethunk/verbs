<?php

namespace Thunk\Verbs\Lifecycle;

use Thunk\Verbs\Event;

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

        if (! app(Store::class)->write($events)) {
            throw new \Exception('Failed to write events to store.');
        }

        $this->event_queue = [];

        return $events;
    }
}