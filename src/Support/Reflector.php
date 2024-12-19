<?php

namespace Thunk\Verbs\Support;

use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Reflector as BaseReflector;
use ReflectionClass;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionParameter;
use Thunk\Verbs\Attributes\Hooks\HookAttribute;
use Thunk\Verbs\Event;
use Thunk\Verbs\Lifecycle\Hook;
use Thunk\Verbs\State;

class Reflector extends BaseReflector
{
    /** @return Collection<int, Hook> */
    public static function getHooks(object $target): Collection
    {
        if ($target instanceof Closure) {
            return collect([Hook::fromClosure($target)]);
        }

        $reflect = new ReflectionClass($target);

        return collect($reflect->getMethods(ReflectionMethod::IS_PUBLIC))
            ->filter(fn (ReflectionMethod $method) => $method->getNumberOfParameters() > 0)
            ->map(fn (ReflectionMethod $method) => Hook::fromClassMethod($target, $method));
    }

    public static function getEventParameters(ReflectionFunctionAbstract|Closure $method): array
    {
        return static::getParametersOfType(Event::class, $method)->values()->all();
    }

    public static function getStateParameters(ReflectionFunctionAbstract|Closure $method): array
    {
        return static::getParametersOfType(State::class, $method)->values()->all();
    }

    public static function applyHookAttributes(ReflectionFunctionAbstract|Closure $method, Hook $hook): Hook
    {
        $method = static::reflectFunction($method);

        foreach ($method->getAttributes() as $attribute) {
            $instance = $attribute->newInstance();
            if ($instance instanceof HookAttribute) {
                $instance->applyToHook($hook);
            }
        }

        return $hook;
    }

    /**
     * This method returns a collection keyed by the parameter position, with a value
     * that is the FQCN of the argument that matches $type
     *
     * @template T
     *
     * @param  class-string<T>  $type
     * @return Collection<int, class-string<T>>
     */
    public static function getParametersOfType(string $type, ReflectionFunctionAbstract|Closure $method): Collection
    {
        $method = static::reflectFunction($method);

        if (empty($parameters = $method->getParameters())) {
            return new Collection;
        }

        return collect($parameters)
            ->map(fn (ReflectionParameter $parameter) => static::getParameterClassNames($parameter))
            ->map(fn (array $names) => array_filter($names, fn ($name) => is_a($name, $type, true)))
            ->reject(fn (array $names) => empty($names))
            ->map(fn (array $names) => Arr::first($names));
    }

    protected static function reflectFunction(ReflectionFunctionAbstract|Closure $function): ReflectionFunctionAbstract
    {
        if ($function instanceof Closure) {
            return new ReflectionFunction($function);
        }

        return $function;
    }
}
