<?php

namespace Thunk\Verbs\Lifecycle;

use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Str;
use ReflectionMethod;
use Thunk\Verbs\Event;
use Thunk\Verbs\Support\Reflector;

class Listener
{
    public static function fromReflection(object $target, ReflectionMethod $method): static
    {
        $listener = new static(
            callback: Closure::fromCallable([$target, $method->getName()]),
            events: Reflector::getEventParameters($method),
        );

        return Reflector::applyAttributes($method, $listener);
    }

    public function __construct(
        public Closure $callback,
        public array $events = [],
        public bool $replayable = true,
    ) {
    }

    public function handle(Event $event, Container $container): void
    {
        $container->call($this->callback, $this->guessEventParameter($event));
    }

    public function replay(Event $event, Container $container): void
    {
        if ($this->replayable) {
            $this->handle($event, $container);
        }
    }

    protected function guessEventParameter(Event $event): array
    {
        // This accounts for a few different naming conventions
        return [
            'event' => $event,
            $event::class => $event,
            (string) Str::of($event::class)->classBasename()->snake() => $event,
            (string) Str::of($event::class)->classBasename()->studly() => $event,
        ];
    }
}
