<?php

namespace Thunk\Verbs\Lifecycle;

use Closure;
use Illuminate\Contracts\Container\Container;
use ReflectionMethod;
use Thunk\Verbs\Event;
use Thunk\Verbs\Support\Reflector;

class Listener
{
    public static function fromReflection(object $target, ReflectionMethod $method): static
    {
        $listener = new static(
            Closure::fromCallable([$target, $method->getName()]),
            Reflector::getEventParameters($method),
        );

        return Reflector::applyAttributes($method, $listener);
    }

    public function __construct(
        public Closure $listener,
        public array $events = [],
        public bool $replayable = true,
    ) {
    }

    public function handle(Event $event, Container $container): void
    {
        $container->call($this->listener, [$event::class => $event]);
    }

    public function replay(Event $event, Container $container): void
    {
        if ($this->replayable) {
            $this->handle($event, $container);
        }
    }
}
