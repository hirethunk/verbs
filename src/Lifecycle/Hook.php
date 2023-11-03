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
        public bool $validates_state = false,
        public bool $aggregates_state = false,
        public ?string $name = null,
    ) {
    }

    public function aggregatesState(bool $aggregates_state = true): static
    {
        $this->aggregates_state = $aggregates_state;

        return $this;
    }

    public function validate(Container $container, Event $event, State $state): bool
    {
        return $container->call($this->callback, $this->guessParameters($event, $state));
    }

    // FIXME: Rename to handle and add 'fired' as its own thing
    public function fire(Container $container, Event $event, State $state = null): void
    {
        // FIXME: Pull states off of events and allow for multiple

        $container->call($this->callback, $this->guessParameters($event, $state));
    }

    public function replay(Container $container, Event $event, State $state = null): void
    {
        // FIXME: Pull states off of events and allow for multiple

        if ($this->replayable) {
            $this->fire($container, $event, $state);
        }
    }

    public function apply(Container $container, Event $event, State $state): void
    {
        $this->fire($container, $event, $state);

        $state->last_event_id = $event->id;
    }

    protected function guessParameters(Event $event, ?State $state): array
    {
        $parameters = [
            'e' => $event,
            'event' => $event,
            $event::class => $event,
            (string) Str::of($event::class)->classBasename()->snake() => $event,
            (string) Str::of($event::class)->classBasename()->studly() => $event,
        ];

        if ($state) {
            $parameters = [
                ...$parameters,
                's' => $state,
                'state' => $state,
                $state::class => $state,
                (string) Str::of($state::class)->classBasename()->snake() => $state,
                (string) Str::of($state::class)->classBasename()->studly() => $state,
            ];
        }

        return $parameters;
    }
}
