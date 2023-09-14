<?php

namespace Thunk\Verbs\Lifecycle;

use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Str;
use ReflectionMethod;
use Thunk\Verbs\Event;
use Thunk\Verbs\State;
use Thunk\Verbs\Support\Reflector;

class Hook
{
    public static function fromClassMethod(object $target, ReflectionMethod $method): static
    {
        $hook = new static(
            callback: Closure::fromCallable([$target, $method->getName()]),
            events: Reflector::getEventParameters($method),
            states: Reflector::getStateParameters($method),
            name: $method->getName(),
        );

        return Reflector::applyAttributes($method, $hook);
    }

    public static function fromClosure(Closure $callback): static
    {
        $hook = new static(
            callback: $callback,
            events: Reflector::getEventParameters($callback),
            states: Reflector::getStateParameters($callback),
        );

        return Reflector::applyAttributes($callback, $hook);
    }

    public function __construct(
        public Closure $callback,
        public array $events = [],
        public array $states = [],
        public bool $replayable = true,
        public ?string $name = null,
    ) {
    }

    public function fire(Container $container, Event $event, State $state = null): void
    {
        $container->call($this->callback, $this->guessParameters($event, $state));
    }

    public function apply(Container $container, Event $event, State $state): void
    {
        $this->fire($container, $event, null);

        // FIXME:
        // $state->last_event_id = $event->id;
    }

    public function replay(Event $event, Container $container): void
    {
        if ($this->replayable) {
            $this->fire($container, $event, null);
        }
    }

    protected function guessParameters(Event $event, ?State $state): array
    {
        return [
            // Daniel is a monster
            'e' => $event,
            's' => $state,

            // Basic name
            'event' => $event,
            'state' => $state,

            // Typehint
            $event::class => $event,
            $state::class => $state,

            // Snake-case name (i.e. MoneyAdded -> $money_added)
            (string) Str::of($event::class)->classBasename()->snake() => $event,
            (string) Str::of($state::class)->classBasename()->snake() => $state,

            // Studly-case name (i.e. MoneyAdded -> $moneyAdded)
            (string) Str::of($event::class)->classBasename()->studly() => $event,
            (string) Str::of($state::class)->classBasename()->studly() => $state,
        ];
    }
}
