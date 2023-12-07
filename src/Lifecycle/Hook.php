<?php

namespace Thunk\Verbs\Lifecycle;

use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Str;
use ReflectionMethod;
use RuntimeException;
use SplObjectStorage;
use Thunk\Verbs\Event;
use Thunk\Verbs\Metadata;
use Thunk\Verbs\State;
use Thunk\Verbs\Support\Reflector;
use Thunk\Verbs\Support\StateCollection;

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

        return Reflector::applyHookAttributes($method, $hook);
    }

    public static function fromClosure(Closure $callback): static
    {
        $hook = new static(
            callback: $callback,
            events: Reflector::getEventParameters($callback),
            states: Reflector::getStateParameters($callback),
        );

        return Reflector::applyHookAttributes($callback, $hook);
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
            return $container->call($this->callback, $this->guessParameters($event, $state)) ?? true;
        }

        throw new RuntimeException('Hook::validate called on a non-validation hook.');
    }

    public function apply(Container $container, Event $event, State $state): void
    {
        if ($this->runsInPhase(Phase::Apply)) {
            $container->call($this->callback, $this->guessParameters($event, $state));
            $state->last_event_id = $event->id;
        }
    }

    public function fired(Container $container, Event $event, StateCollection $states): void
    {
        if ($this->runsInPhase(Phase::Fired)) {
            $container->call($this->callback, $this->guessParameters($event, states: $states));
        }
    }
	
    public function handle(Container $container, Event $event, Metadata $metadata, ?State $state = null): void
    {
        if ($this->runsInPhase(Phase::Handle)) {
            $container->call($this->callback, array_merge($this->guessParameters($event, $state), [Metadata::class => $metadata]));
        }
    }

    public function replay(Container $container, Event $event, ?State $state = null): void
    {
        if ($this->runsInPhase(Phase::Replay)) {
            $container->call($this->callback, $this->guessParameters($event, $state));
        }
    }

    protected function guessParameters(Event $event, ?State $state = null, ?StateCollection $states = null): array
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

        if ($states) {
            foreach ($states as $state) {
                $keys = [
                    $state::class,
                    (string) Str::of($state::class)->classBasename()->snake(),
                    (string) Str::of($state::class)->classBasename()->studly(),
                ];

                // FIXME: We need to throw an ambiguous state exception if a method wants a state that we have 2+ of
                //        But right now, we'll just null them out
                if (isset($parameters[$state::class])) {
                    $state = null;
                }

                foreach ($keys as $key) {
                    $parameters[$key] = $state;
                }
            }
        }

        // We're going to null out any parameters that may be ambiguous, so we need to filter them here
        return array_filter($parameters);
    }
}
