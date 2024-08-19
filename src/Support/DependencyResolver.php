<?php

namespace Thunk\Verbs\Support;

use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Collection;
use ReflectionFunction;
use ReflectionParameter;
use Thunk\Verbs\Event;
use Thunk\Verbs\Exceptions\AmbiguousDependencyException;
use Thunk\Verbs\Support\Reflection\Parameter;
use WeakMap;

class DependencyResolver
{
    protected Collection $candidates;

    public static function for(callable $callback, ?Container $container = null, ?Event $event = null): static
    {
        $resolver = new static(
            container: $container ?? \Illuminate\Container\Container::getInstance(),
            callback: $callback(...),
        );

        if ($event) {
            $resolver->addEvent($event);
        }

        return $resolver;
    }

    public function __construct(
        protected Container $container,
        protected Closure $callback,
    ) {
        $this->candidates = new Collection;
    }

    public function add(mixed $candidate, ?string $name = null): static
    {
        if ($name) {
            $candidate = new NamedDependency($name, $candidate);
        }

        $this->candidates->push($candidate);

        return $this;
    }

    public function addEvent(Event $event): static
    {
        $this->add($event);
        $this->add($event->metadata());

        $this->addStates($event->states());

        return $this;
    }

    public function addStates(StateCollection $states): static
    {
        $added = new WeakMap;

        // Add states by alias for named resolution
        foreach ($states->aliasNames() as $name) {
            $state = $states->get($name);
            $added[$state] = true;
            $this->add($state, $name);
        }

        // Then add any other states that do not have aliases
        foreach ($states as $state) {
            if (! isset($added[$state])) {
                $this->add($state);
            }
        }

        return $this;
    }

    public function __invoke(): array
    {
        return array_map(
            $this->resolveParameter(...),
            (new ReflectionFunction($this->callback))->getParameters()
        );
    }

    protected function resolveParameter(ReflectionParameter $reflection): mixed
    {
        $parameter = new Parameter($reflection);

        $candidates = $this->candidates->filter($parameter->accepts(...));

        $resolved = match ($candidates->count()) {
            0 => $this->container->make($parameter->type()->name()),
            1 => $candidates->first(),
            default => $this->resolveAmbiguousParameter($parameter, $candidates),
        };

        if ($resolved instanceof NamedDependency) {
            $resolved = $resolved->value;
        }

        return $resolved;
    }

    protected function resolveAmbiguousParameter(Parameter $parameter, Collection $candidates): mixed
    {
        $resolved = $candidates->first(
            fn ($candidate) => $candidate instanceof NamedDependency && $candidate->name === $parameter->name
        );

        if (! $resolved) {
            throw new AmbiguousDependencyException($parameter, $candidates);
        }

        return $resolved;
    }
}
