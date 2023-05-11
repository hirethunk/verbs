<?php

namespace Thunk\Verbs\Lifecycle;

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
    ) {
    }

    public function originate(Event $event, ?Context $context = null): void
    {
        $context = $this->syncOrCreateContext($event, $context);

        $this->guardEvent($event, $context);

        $this->events->save($event);
        $this->bus->dispatch($event);
    }

    public function replay(array|string $event_types = null, int $chunk_size = 1000): void
    {
        $this->events
            ->get((array) $event_types, null, null, $chunk_size)
            ->each($this->bus->replay(...));
    }

    protected function syncOrCreateContext(Event $event, ?Context $context = null): ?Context
    {
        if ($creates = Reflector::getContextForCreation($event)) {
            // If we've been provided a context, but the event that we're firing also
            // creates a new context, we want to trigger an error before the event fires
            if ($context) {
                throw new ContextAlreadyExists($event, $context, $creates);
            }

            $context = new $creates($this->snowflakes->make());
        }

        if ($context) {
            $event->context_id = $context->id;
            $this->contexts->register($context);
        }

        return $context;
    }

    protected function guardEvent(Event $event, ?Context $context): void
    {
        Guards::for($event, $context)->check();

        if ($context) {
            $this->contexts->validate($context, $event);
        }
    }
}
