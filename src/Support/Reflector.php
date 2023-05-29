<?php

namespace Thunk\Verbs\Support;

use Closure;
use Illuminate\Support\Collection;
use ReflectionClass;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use Thunk\Verbs\Attributes\CreatesContext;
use Thunk\Verbs\Attributes\ListenerAttribute;
use Thunk\Verbs\Event;
use Thunk\Verbs\Lifecycle\Listener;

class Reflector extends \Illuminate\Support\Reflector
{
    /** @return Collection<int, Listener> */
    public static function getListeners(object $target): Collection
    {
        if ($target instanceof Closure) {
            return collect([Listener::fromClosure($target)]);
        }

        $reflect = new ReflectionClass($target);

        return collect($reflect->getMethods(ReflectionMethod::IS_PUBLIC))
            ->filter(fn (ReflectionMethod $method) => $method->getNumberOfParameters())
            ->map(fn (ReflectionMethod $method) => Listener::fromReflection($target, $method));
    }

    public static function getContextForCreation(object $target): ?string
    {
        $reflect = new ReflectionClass($target);
        $attributes = $reflect->getAttributes(CreatesContext::class);

        if (! count($attributes)) {
            return null;
        }

        return $attributes[0]->getArguments()[0];
    }

    public static function getEventParameters(ReflectionFunctionAbstract|Closure $method): array
    {
        $method = static::reflectFunction($method);

        if (empty($parameters = $method->getParameters())) {
            return [];
        }

        return array_filter(
            array: Reflector::getParameterClassNames($parameters[0]),
            callback: fn (string $class_name) => is_a($class_name, Event::class, true)
        );
    }

    public static function applyAttributes(ReflectionFunctionAbstract|Closure $method, Listener $listener): Listener
    {
        $method = static::reflectFunction($method);

        foreach ($method->getAttributes() as $attribute) {
            $instance = $attribute->newInstance();
            if ($instance instanceof ListenerAttribute) {
                $instance->applyToListener($listener);
            }
        }

        return $listener;
    }

    protected static function reflectFunction(ReflectionFunctionAbstract|Closure $function): ReflectionFunctionAbstract
    {
        if ($function instanceof Closure) {
            return new ReflectionFunction($function);
        }

        return $function;
    }
}