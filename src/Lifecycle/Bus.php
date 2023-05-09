<?php

namespace Thunk\Verbs\Lifecycle;

use Illuminate\Contracts\Container\Container;
use ReflectionMethod;
use Thunk\Verbs\Contracts\DispatchesEvents;
use Thunk\Verbs\Event;
use Thunk\Verbs\Support\Reflector;

class Bus implements DispatchesEvents
{
    protected array $listeners = [];

    public function __construct(
        protected Container $container
    ) {
    }

    public function listen(object $listener): void
    {
        foreach (Reflector::getListeners($listener) as $listener) {
            foreach ($listener->events as $event_type) {
                $this->listeners[$event_type][] = $listener;
            }
        }
    }

    public function dispatch(Event $event): void
    {
        foreach ($this->getListeners($event) as $listener) {
            $listener->handle($event, $this->container);
        }
    }

    public function replay(Event $event): void
    {
        foreach ($this->getListeners($event) as $listener) {
            $listener->replay($event, $this->container);
        }
    }

    /** @return \Thunk\Verbs\Lifecycle\Listener[] */
    protected function getListeners(Event $event): array
    {
        $listeners = $this->listeners[$event::class] ?? [];
        
        // Maybe "always"
        if (method_exists($event, 'onFire')) {
            $onFire = Listener::fromReflection($event, new ReflectionMethod($event, 'onFire'));
            array_unshift($listeners, $onFire);
        }

        // Maybe "once"
        if (method_exists($event, 'onFirstFire')) {
            $onFirstFire = Listener::fromReflection($event, new ReflectionMethod($event, 'onFirstFire'));
            $onFirstFire->replayable = false;
            array_unshift($listeners, $onFirstFire);
        }

        return $listeners;
    }
}
