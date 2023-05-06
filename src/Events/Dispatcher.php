<?php

namespace Thunk\Verbs\Events;

use Illuminate\Contracts\Container\Container;
use ReflectionMethod;
use Thunk\Verbs\Events\Dispatcher\Listener;
use Thunk\Verbs\Support\Reflection\Reflector;

class Dispatcher
{
    public function __construct(
        protected Container $container,
        protected array $listeners = [],
    ) {
    }

    public function registerListener(object $listener): void
    {
        Reflector::getListeners($listener)
            ->each(function (Listener $listener) {
                foreach ($listener->events as $event_type) {
                    $this->listeners[$event_type][] = $listener;
                }
            });
    }

    public function fire(Event $event): void
    {
        foreach ($this->getListeners($event) as $listener) {
            $listener($event);
        }
    }

    public function replay(Event $event): void
    {
        foreach ($this->getReplayListeners($event) as $listener) {
            $listener($event);
        }
    }

    protected function getListeners(Event $event)
    {
        $listeners = $this->listeners[$event::class] ?? [];

        if (method_exists($event, 'onFire')) {
            $onFire = Listener::fromReflection($event, new ReflectionMethod($event, 'onFire'));
            array_unshift($listeners, $onFire);
        }

        return $listeners;
    }

    protected function getReplayListeners(Event $event)
    {
        return array_filter($this->getListeners($event), fn (Listener $listener) => ! $listener->once);
    }
}
