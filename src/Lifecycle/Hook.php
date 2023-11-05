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
    public static function fromClassMethod(object $target, ReflectionMethod|string $method): static
    {
        if (is_string($method)) {
            $method = new ReflectionMethod($target, $method);
        }

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
        public bool $when_fired = false,
        public bool $runs_on_commit = false,
        public bool $replayable = false,
        public bool $validates_state = false,
        public bool $aggregates_state = false,
        public ?string $name = null,
    ) {
    }

    public function whenFired(): static
    {
        $this->when_fired = true;

        return $this;
    }

    public function runsOnCommit(): static
    {
        $this->runs_on_commit = true;

        return $this;
    }

    public function replayable(): static
    {
        $this->replayable = true;

        return $this;
    }

    public function validatesState(): static
    {
        $this->validates_state = true;

        return $this;
    }

    public function aggregatesState(): static
    {
        $this->aggregates_state = true;

        return $this;
    }

    public function validate(Container $container, Event $event, State $state): bool
    {
        if ($this->validates_state) {
            return $container->call($this->callback, $this->guessParameters($event, $state)) ?? false;
        }

        return false;
    }

    public function apply(Container $container, Event $event, State $state): void
    {
        if ($this->aggregates_state) {
            $container->call($this->callback, $this->guessParameters($event, $state));
            $state->last_event_id = $event->id;
        }
    }

    public function fired(Container $container, Event $event, State $state = null): void
    {
        if ($this->when_fired) {
            $container->call($this->callback, $this->guessParameters($event, $state));
        }
    }

    public function handle(Container $container, Event $event, State $state = null): void
    {
        if ($this->runs_on_commit) {
            $container->call($this->callback, $this->guessParameters($event, $state));
        }
    }

    public function replay(Container $container, Event $event, State $state = null): void
    {
        if ($this->replayable) {
            $container->call($this->callback, $this->guessParameters($event, $state));
        }
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
