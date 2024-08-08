<?php

namespace Thunk\Verbs\Support;

use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Collection;
use ReflectionFunction;
use ReflectionParameter;
use Thunk\Verbs\Exceptions\AmbiguousDependencyException;
use Thunk\Verbs\Support\Reflection\Parameter;

class DependencyResolver
{
    public static function for(Closure $callback, ?Collection $candidates = null, ?Container $container = null): static
    {
        return new static(
            container: $container ?? \Illuminate\Container\Container::getInstance(),
            callback: $callback,
            candidates: $candidates ?? new Collection(),
        );
    }

    public function __construct(
        protected Container $container,
        protected Closure $callback,
        protected Collection $candidates,
    ) {}

    public function add(mixed $candidate, ?string $name = null): static
    {
        if ($name) {
            $candidate = new NamedDependency($name, $candidate);
        }

        $this->candidates->push($candidate);

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
