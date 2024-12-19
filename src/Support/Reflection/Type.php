<?php

namespace Thunk\Verbs\Support\Reflection;

use Illuminate\Support\Traits\ForwardsCalls;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionUnionType;
use Thunk\Verbs\Exceptions\CannotResolveParameter;

/** @mixin ReflectionNamedType */
class Type
{
    use ForwardsCalls;

    public ReflectionNamedType $target;

    public function __construct(
        public Parameter $parameter,
    ) {
        if (! $type = $parameter->target->getType()) {
            throw new CannotResolveParameter('You must provide a parameter type for Verbs to inject.');
        }

        // FIXME: We need to add support for union types here now

        if ($type instanceof ReflectionIntersectionType || $type instanceof ReflectionUnionType) {
            throw new CannotResolveParameter('Verbs cannot inject intersection or union types.');
        }

        $this->target = $this->parameter->target->getType();
    }

    public function name(): string
    {
        $name = $this->target->getName();

        return match ($name) {
            'self' => $this->parameter->target->getDeclaringClass()->getName(),
            'parent' => $this->parameter->target->getDeclaringClass()->getParentClass()->getName(),
            default => $name,
        };
    }

    public function __call(string $name, array $arguments)
    {
        return $this->forwardDecoratedCallTo($this->target, $name, $arguments);
    }
}
