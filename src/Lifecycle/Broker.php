<?php

namespace Thunk\Verbs\Lifecycle;

use Ev;
use Thunk\Verbs\Context;
use Thunk\Verbs\Contracts\BrokersEvents;
use Thunk\Verbs\Contracts\DispatchesEvents;
use Thunk\Verbs\Contracts\ManagesContext;
use Thunk\Verbs\Contracts\StoresEvents;
use Thunk\Verbs\Event;
use Thunk\Verbs\Exceptions\ContextAlreadyExists;
use Thunk\Verbs\Snowflakes\Factory;
use Thunk\Verbs\Support\Reflector;

class Broker implements BrokersEvents
{
    public function __construct(
        protected DispatchesEvents $bus,
        protected StoresEvents $events,
        protected ManagesContext $contexts,
        protected Factory $snowflakes,
    )
    {
    }

    public function originate(Event $event, ?Context $context = null): void
    {
        $context = $this->getContext($event, $context);

        // First we'll check that the event CAN be fired
        Guards::for($event, $context)->check();
        
        // Then, if there is a context, we'll validate the event for concurrency issues
        if ($context) {
            $this->contexts->validate($context, $event);
        }
        
        // If all goes well, we can store the event and dispatch it
        $this->events->save($event);
        $this->bus->dispatch($event);
    }

    public function replay(array|string $event_types = null, int $chunk_size = 1000): void
    {
        $this->events
            ->get((array) $event_types, null, null, $chunk_size)
            ->each($this->bus->replay(...));
    }
    
    protected function getContext(Event $event, ?Context $existing = null): ?Context
    {
        if (! $creates = Reflector::getContextForCreation($event)) {
            return $existing;
        }
        
        if ($existing) {
            throw new ContextAlreadyExists($event, $existing, $creates);
        }

        $context = new $creates($this->snowflakes->make());

        return $this->contexts->register($context);
    }
}
