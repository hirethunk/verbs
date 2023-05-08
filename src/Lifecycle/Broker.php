<?php

namespace Thunk\Verbs\Lifecycle;

use Thunk\Verbs\Contracts;
use Thunk\Verbs\Event;

class Broker implements Contracts\Broker
{
    public function __construct(
        protected Contracts\Bus $bus,
        protected Contracts\EventRepository $events,
        protected Contracts\ContextRepository $contexts,
    ) {
    }

    public function fire(Event $event): void
    {
        Guards::for($event)->check();

        $this->contexts->apply($event);
        $this->events->save($event);
        $this->bus->dispatch($event);
    }

    public function replay(array|string $event_types = null, int $chunk_size = 1000): void
    {
        $this->events
            ->get((array) $event_types, null, $chunk_size)
            ->each($this->bus->replay(...));
    }
}
