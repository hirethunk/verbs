<?php

namespace Thunk\Verbs\Lifecycle;

use ReflectionClass;
use ReflectionMethod;
use RuntimeException;
use Thunk\Verbs\Attributes\CreatesContext;
use Thunk\Verbs\Contracts\BrokersEvents;
use Thunk\Verbs\Contracts\DispatchesEvents;
use Thunk\Verbs\Contracts\ManagesContext;
use Thunk\Verbs\Contracts\StoresEvents;
use Thunk\Verbs\Event;
use Thunk\Verbs\Facades\Snowflake;

class Broker implements BrokersEvents
{
    public function __construct(
        protected DispatchesEvents $bus,
        protected StoresEvents $events,
        protected ManagesContext $contexts,
    ) {
    }

    public function originate(Event $event): void
    {
        // TODO: This is ugly just to prove the concept
        if (count($attributes = (new ReflectionClass($event))->getAttributes(CreatesContext::class))) {
            $context = $attributes[0]->getArguments()[0];
            
            if ($event->context) {
                throw new RuntimeException('Trying to create context when on is already attached.');
            }
            
            $event->context = new $context(Snowflake::make());
        }
        
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
                // FIXME: We need the context ID here
                $this->contexts->apply($event);
                $this->bus->replay($event);
            });
    }
}
