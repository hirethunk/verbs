<?php

namespace Thunk\Verbs\Lifecycle;

use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Str;
use InvalidArgumentException;
use ReflectionMethod;
use RuntimeException;
use SplObjectStorage;
use Thunk\Verbs\Event;
use Thunk\Verbs\Metadata;
use Thunk\Verbs\State;
use Thunk\Verbs\Support\Reflector;
use Thunk\Verbs\Support\StateCollection;
use Thunk\Verbs\Support\Wormhole;

class Hook
{
    public static function fromClassMethod(string|object $target, ReflectionMethod|string $method): static
    {
        if (is_string($target) && ! class_exists($target)) {
            throw new InvalidArgumentException('Hooks can only be registered for objects or classes.');
        }

        if (is_string($method)) {
            $method = new ReflectionMethod($target, $method);
        }

        $hook = new static(
            callback: is_string($target)
                ? fn (...$args) => app($target)->{$method->getName()}(...$args)
                : Closure::fromCallable([$target, $method->getName()]),
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
    ) {}

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

    public function inferPhasesIfNoneSet(): void
    {
        // If we already have phases set, we don't need to do anything
        if ($this->phases->count() > 0) {
            return;
        }

        // Assume that if the hook cares about events, it's a handle hook,
        // and if it cares about states, it's an apply hook
        if (count($this->events)) {
            $this->forcePhases(Phase::Handle);
        } elseif (count($this->states)) {
            $this->forcePhases(Phase::Apply);
        }
    }

    public function validate(Container $container, Event $event, ?State $state = null): bool
    {
        if ($this->runsInPhase(Phase::Validate)) {
            return $container->call($this->callback, $this->guessParameters($event, $state)) ?? true;
        }

        throw new RuntimeException('Hook::validate called on a non-validation hook.');
    }

    public function apply(Container $container, Event $event, State $state): void
    {
        if ($this->runsInPhase(Phase::Apply)) {
            app(Wormhole::class)->warp($event, fn () => $container->call($this->callback, $this->guessParameters($event, $state)));
        }
    }

    public function fired(Container $container, Event $event, StateCollection $states): void
    {
        if ($this->runsInPhase(Phase::Fired)) {
            $container->call($this->callback, $this->guessParameters($event, states: $states));
        }
    }

    public function handle(Container $container, Event $event, StateCollection $states): mixed
    {
        if ($this->runsInPhase(Phase::Handle)) {
            return $container->call($this->callback, $this->guessParameters($event, states: $states));
        }

        return null;
    }

    public function replay(Container $container, Event $event, StateCollection $states): void
    {
        if ($this->runsInPhase(Phase::Replay)) {
            app(Wormhole::class)->warp($event, fn () => $container->call($this->callback, $this->guessParameters($event, states: $states)));
        }
    }

    protected function guessParameters(Event $event, ?State $state = null, ?StateCollection $states = null): array
    {
        $metadata = $event->metadata();

        $parameters = [
            'e' => $event,
            'event' => $event,
            $event::class => $event,
            (string) Str::of($event::class)->classBasename()->snake() => $event,
            (string) Str::of($event::class)->classBasename()->studly() => $event,
            'meta' => $metadata,
            'metadata' => $metadata,
            'metaData' => $metadata,
            'meta_data' => $metadata,
            Metadata::class => $metadata,
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
                if (array_key_exists($state::class, $parameters)) {
                    $state = null;
                }

                foreach ($keys as $key) {
                    $parameters[$key] = $state;
                }
            }

            foreach ($states->aliasNames() as $alias) {
                $parameters[$alias] = $states->get($alias);
            }
        }

        return $parameters;
    }
}
