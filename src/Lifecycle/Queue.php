<?php

namespace Thunk\Verbs\Lifecycle;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
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

        // TODO: Concurrency check

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

    public function __destruct()
    {
        if (count($this->event_queue) && App::has('log')) {
            Log::error(
                message: 'The Verbs event queue was destroyed before it was flushed. You may have forgotten Verbs::commit().',
                context: ['event_queue' => $this->event_queue],
            );
        }
    }
}
