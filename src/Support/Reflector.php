<?php

namespace Thunk\Verbs\Support;

use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Reflector as BaseReflector;
use ReflectionClass;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use Thunk\Verbs\Attributes\ListenerAttribute;
use Thunk\Verbs\Event;
use Thunk\Verbs\Lifecycle\Listener;

class Reflector extends BaseReflector
{
    /** @return Collection<int, Listener> */
    public static function getListeners(object $target): Collection
    {
        if ($target instanceof Closure) {
            return collect([Listener::fromClosure($target)]);
        }

        $reflect = new ReflectionClass($target);

        return collect($reflect->getMethods(ReflectionMethod::IS_PUBLIC))
            ->filter(fn (ReflectionMethod $method) => $method->getNumberOfParameters() > 0)
            ->map(fn (ReflectionMethod $method) => Listener::fromClassMethod($target, $method));
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
