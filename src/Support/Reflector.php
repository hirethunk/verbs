<?php

namespace Thunk\Verbs\Support;

use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Reflector as BaseReflector;
use InvalidArgumentException;
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
    public static function getHooks(string|object $target): Collection
    {
        if (is_string($target) && ! class_exists($target)) {
            throw new InvalidArgumentException('Hooks can only be registered for objects or classes.');
        }

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

    public static function getParameterTypes(ReflectionFunctionAbstract|Closure $method): array
    {
        $method = static::reflectFunction($method);

        if (empty($parameters = $method->getParameters())) {
            return [];
        }

        return Collection::make($parameters)
            ->map(fn (ReflectionParameter $parameter) => static::getParameterClassNames($parameter))
            ->flatten()
            ->filter()
            ->unique()
            ->values()
            ->toArray();
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
            ->flatten()
            ->filter(fn ($class_name) => is_a($class_name, $type, true));
    }

    /** @return class-string[] */
    public static function getClassInstanceOf(string|object $class): array
    {
        $reflection = new ReflectionClass($class);

        $class_and_interface_names = array_unique($reflection->getInterfaceNames());

        do {
            $class_and_interface_names[] = $reflection->getName();
        } while ($reflection = $reflection->getParentClass());

        return $class_and_interface_names;
    }

    /**
     * A constructor-promoted property keeps its default on the constructor parameter,
     * not on the property, so `ReflectionProperty::hasDefaultValue()` reports false for
     * it and `newInstanceWithoutConstructor()` leaves it uninitialized.
     *
     * @return array<string, mixed>
     */
    public static function getPromotedDefaults(ReflectionClass $reflect): array
    {
        $defaults = [];

        foreach ($reflect->getConstructor()?->getParameters() ?? [] as $parameter) {
            if ($parameter->isPromoted() && $parameter->isDefaultValueAvailable()) {
                $defaults[$parameter->getName()] = $parameter->getDefaultValue();
            }
        }

        return $defaults;
    }

    /** Apply promoted defaults to any property an instance hasn't had set yet. */
    public static function applyPromotedDefaults(object $instance, ?ReflectionClass $reflect = null): void
    {
        $reflect ??= new ReflectionClass($instance);

        foreach (static::getPromotedDefaults($reflect) as $name => $default) {
            $property = $reflect->getProperty($name);

            if (! $property->isInitialized($instance)) {
                $property->setValue($instance, $default);
            }
        }
    }

    protected static function reflectFunction(ReflectionFunctionAbstract|Closure $function): ReflectionFunctionAbstract
    {
        if ($function instanceof Closure) {
            return new ReflectionFunction($function);
        }

        return $function;
    }
}
