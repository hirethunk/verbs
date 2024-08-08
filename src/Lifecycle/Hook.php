<?php

namespace Thunk\Verbs\Lifecycle;

use Closure;
use Illuminate\Contracts\Container\Container;
use ReflectionMethod;
use RuntimeException;
use SplObjectStorage;
use Thunk\Verbs\Event;
use Thunk\Verbs\State;
use Thunk\Verbs\Support\DependencyResolver;
use Thunk\Verbs\Support\Reflector;
use Thunk\Verbs\Support\StateCollection;
use Thunk\Verbs\Support\Wormhole;
use WeakMap;

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

    public function validate(Container $container, Event $event, ?State $state = null): bool
    {
        if ($this->runsInPhase(Phase::Validate)) {
            return $this->call($container, $event) ?? true;
        }

        throw new RuntimeException('Hook::validate called on a non-validation hook.');
    }

    public function apply(Container $container, Event $event, State $state): void
    {
        if ($this->runsInPhase(Phase::Apply)) {
            app(Wormhole::class)->warp($event, fn () => $this->call($container, $event));
        }
    }

    public function fired(Container $container, Event $event, StateCollection $states): void
    {
        if ($this->runsInPhase(Phase::Fired)) {
            $this->call($container, $event);
        }
    }

    public function handle(Container $container, Event $event, StateCollection $states): mixed
    {
        if ($this->runsInPhase(Phase::Handle)) {
            $this->call($container, $event);
        }

        return null;
    }

    public function replay(Container $container, Event $event, StateCollection $states): void
    {
        if ($this->runsInPhase(Phase::Replay)) {
            app(Wormhole::class)->warp($event, fn () => $this->call($container, $event));
        }
    }

    protected function call(Container $container, Event $event)
    {
        $resolver = DependencyResolver::for($this->callback, container: $container)
            ->add($event->metadata())
            ->add($event);

        $this->addStatesToResolver($resolver, $event->states());

        return call_user_func_array($this->callback, $resolver());
    }

    protected function addStatesToResolver(DependencyResolver $resolver, StateCollection $states): DependencyResolver
    {
        $added = new WeakMap();

        // Add states by alias for named resolution
        foreach ($states->aliasNames() as $name) {
            $state = $states->get($name);
            $added[$state] = true;
            $resolver->add($state, $name);
        }

        // Then add any other states that do not have aliases
        foreach ($states as $state) {
            if (! isset($added[$state])) {
                $resolver->add($state);
            }
        }

        return $resolver;
    }
}
