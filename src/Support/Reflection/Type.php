<?php

namespace Thunk\Verbs\Support\Reflection;

use BadMethodCallException;
use Illuminate\Support\Arr;
use Illuminate\Support\Traits\ForwardsCalls;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;
use Thunk\Verbs\Exceptions\CannotResolveParameter;
use Thunk\Verbs\Support\NamedDependency;

/** @mixin ReflectionNamedType */
class Type
{
    use ForwardsCalls;

    /** @var ReflectionNamedType[] */
    protected array $targets;

    public function __construct(
        public Parameter $parameter,
    ) {
        if (! $type = $parameter->target->getType()) {
            throw new CannotResolveParameter('You must provide a parameter type for Verbs to inject.');
        }

        $this->targets = $this->unwrapTypes($type);
    }

    public function includes(mixed $value): bool
    {
        if ($value instanceof NamedDependency) {
            $value = $value->value;
        }

        foreach ($this->targets as $type) {
            if ($type->isBuiltin() && get_debug_type($value) === $this->resolveName($type)) {
                return true;
            }

            if (! $type->isBuiltin() && is_a($value, $this->resolveName($type), true)) {
                return true;
            }
        }

        return false;
    }

    public function name(): string
    {
        if (count($this->targets) > 1) {
            throw new BadMethodCallException('Cannot get the name of a union type.');
        }

        $name = Arr::first($this->targets)->getName();

        return match ($name) {
            'self' => $this->parameter->target->getDeclaringClass()->getName(),
            'parent' => $this->parameter->target->getDeclaringClass()->getParentClass()->getName(),
            default => $name,
        };
    }

    public function __call(string $name, array $arguments)
    {
        return $this->forwardDecoratedCallTo($this->parameter->getType(), $name, $arguments);
    }

    protected function resolveName(ReflectionNamedType $type): string
    {
        return match ($type->getName()) {
            'self' => $this->parameter->target->getDeclaringClass()->getName(),
            'parent' => $this->parameter->target->getDeclaringClass()->getParentClass()->getName(),
            default => $type->getName(),
        };
    }

    protected function unwrapTypes(ReflectionType $type): array
    {
        $unwrapped = [];

        if ($type instanceof ReflectionNamedType) {
            $unwrapped[] = $type;
        } elseif ($type instanceof ReflectionUnionType) {
            foreach ($type->getTypes() as $type) {
                $unwrapped = array_merge($unwrapped, $this->unwrapTypes($type));
            }
        } elseif ($type instanceof ReflectionIntersectionType) {
            throw new CannotResolveParameter('Verbs cannot resolve intersection types.');
        } else {
            throw new CannotResolveParameter('Verbs encountered an unknown type: '.class_basename($type));
        }

        return array_unique($unwrapped);
    }
}
