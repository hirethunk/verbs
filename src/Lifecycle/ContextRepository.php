<?php

namespace Thunk\Verbs\Lifecycle;

use Illuminate\Contracts\Container\Container;
use Thunk\Verbs\Context;
use Thunk\Verbs\Contracts\ManagesContext;
use Thunk\Verbs\Contracts\StoresEvents;
use Thunk\Verbs\Event;
use Thunk\Verbs\Support\Reflector;

class ContextRepository implements ManagesContext
{
    protected array $contexts = [];

    public function __construct(
        protected Container $container,
        protected StoresEvents $events,
    )
    {
    }

    public function register(Context $context): Context
    {
        $this->contexts[$context::class][$context->id->id()] = $context;
        
        // FIXME: Check that there are no events already stored for this context, since it should be completely new

        return $context;
    }

    public function validate(Context $context, Event $event): void
    {
        // FIXME
    }

    public function sync(Context $context): Context
    {
        $listeners = Reflector::getListeners($context);

        $this->events->get(context_id: $context->id)
            ->each(fn(Event $event) => $listeners
                ->filter(fn(Listener $listener) => $listener->handles($event))
                ->each(fn(Listener $listener) => $listener->apply($event, $context, $this->container)));

        $this->register($context);
        
        return $context;
    }
}
