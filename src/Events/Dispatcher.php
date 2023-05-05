<?php

namespace Thunk\Verbs\Events;

use Closure;
use Illuminate\Contracts\Container\Container;
use ReflectionMethod;
use Thunk\Verbs\Events\Dispatcher\Listener;
use Thunk\Verbs\Support\Reflection\ListenerMethod;
use Thunk\Verbs\Support\Reflection\Reflector;

class Dispatcher
{
    protected bool $replaying = false;

    public function __construct(
        protected Container $container,
        protected array $listeners = [],
    ) {
    }

    public function isReplaying(): bool
    {
        return $this->replaying;
    }

    public function whenReplaying(callable $callback): void
    {
        if ($this->replaying) {
            $callback();
        }
    }

    public function unlessReplaying(callable $callback): void
    {
        if (! $this->replaying) {
            $callback();
        }
    }

    public function registerListener(object $listener): void
    {
        Reflector::getListenerMethods($listener)
            ->each(function (ListenerMethod $method) {
                $listener = Closure::fromCallable([$method->listener, $method->method_name]);
                $this->listen($method->event_type, $listener, $method->once);
            });
    }

    public function listen(string $event_type, Closure $listener, bool $once = false)
    {
        $this->listeners[$event_type][] = new Listener($listener, $once);
    }

    public function fire(Event $event): void
    {
        Lifecycle::for($event)
            ->authorize()
            ->validate();

        foreach ($this->getListeners($event) as $listener) {
            $listener($event);
        }
    }

    public function replay(Event $event): void
    {
        try {
            $this->replaying = true;

            foreach ($this->getReplayListeners($event) as $listener) {
                $listener($event);
            }
        } finally {
            $this->replaying = false;
        }
    }

    protected function getListeners(Event $event)
    {
        $listeners = $this->listeners[$event::class] ?? [];

        // Add an 'onFire' listener if the event has one
        if (method_exists($event, 'onFire')) {
            $listeners[] = new Listener(
                listener: $event->onFire(...),
                once: Reflector::hasOnceAttribute(new ReflectionMethod($event, 'onFire')),
            );
        }

        return $listeners;
    }

    protected function getReplayListeners(Event $event)
    {
        return array_filter($this->getListeners($event), fn (Listener $listener) => ! $listener->once);
    }
}
