<?php

namespace Thunk\Verbs\Lifecycle;

use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Str;
use ReflectionMethod;
use SplObjectStorage;
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
        public SplObjectStorage $phases = new SplObjectStorage(),
        public ?string $name = null,
    ) {
    }

    public function forcePhases(Phase ...$phases): static
    {
        foreach ($phases as $phase) {
            $this->phases[$phase] = true;
        }

        return $this;
    }

    public function skipPhases(Phase ...$phases): static
    {
        foreach ($phases as $phase) {
            $this->phases[$phase] = false;
        }

        return $this;
    }

    public function runsInPhase(Phase $phase): bool
    {
        return isset($this->phases[$phase]) && $this->phases[$phase] === true;
    }

    public function validate(Container $container, Event $event, State $state): bool
    {
        if ($this->runsInPhase(Phase::Validate)) {
            return $container->call($this->callback, $this->guessParameters($event, $state)) ?? false;
        }

        return false;
    }

    public function apply(Container $container, Event $event, State $state): void
    {
        if ($this->runsInPhase(Phase::Apply)) {
            $container->call($this->callback, $this->guessParameters($event, $state));
            $state->last_event_id = $event->id;
        }
    }

    public function fired(Container $container, Event $event, State $state = null): void
    {
        if ($this->runsInPhase(Phase::Fired)) {
            $container->call($this->callback, $this->guessParameters($event, $state));
        }
    }

    public function handle(Container $container, Event $event, State $state = null): void
    {
        if ($this->runsInPhase(Phase::Handle)) {
            $container->call($this->callback, $this->guessParameters($event, $state));
        }
    }

    public function replay(Container $container, Event $event, State $state = null): void
    {
        if ($this->runsInPhase(Phase::Replay)) {
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
