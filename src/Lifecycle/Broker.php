<?php

namespace Thunk\Verbs\Lifecycle;

use Thunk\Verbs\Contracts\BrokersEvents;
use Thunk\Verbs\Contracts\DispatchesEvents;
use Thunk\Verbs\Contracts\ManagesContext;
use Thunk\Verbs\Contracts\StoresEvents;
use Thunk\Verbs\Event;

class Broker implements BrokersEvents
{
    public function __construct(
        protected DispatchesEvents $bus,
        protected StoresEvents $events,
        protected ManagesContext $contexts,
    )
    {
    }

    public function originate(Event $event): void
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
            ->each(function (Event $event) {
                $this->contexts->apply($event);
                $this->bus->replay($event);
            });
    }
}
