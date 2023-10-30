<?php

namespace Thunk\Verbs\Support;

use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Reflector as BaseReflector;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionProperty;
use Thunk\Verbs\Attributes\Hooks\HookAttribute;
use Thunk\Verbs\Attributes\StateDiscovery\DependsOnDiscoveredState;
use Thunk\Verbs\Attributes\StateDiscovery\ReflectsProperty;
use Thunk\Verbs\Attributes\StateDiscovery\StateDiscoveryAttribute;
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

    public static function getPublicStateProperties(Event $event)
    {
        $reflect = new ReflectionClass($event);

        return collect($reflect->getProperties(ReflectionMethod::IS_PUBLIC))
            ->filter(function (ReflectionProperty $prop) {
                $type = $prop->getType();

                return $type instanceof ReflectionNamedType
                    && ! $type->isBuiltin()
                    && is_a($type->getName(), State::class, true);
            })
            ->mapWithKeys(fn (ReflectionProperty $prop) => [
                $prop->getName() => $prop->getValue($event),
            ]);
    }

    public static function getNonStatePublicPropertiesAndValues(Event $event)
    {
        $properties = get_object_vars($event);
        $reflect = new ReflectionClass($event);

        return collect($reflect->getProperties(ReflectionMethod::IS_PUBLIC))
            ->filter(function (ReflectionProperty $prop) {
                $type = $prop->getType();

                return $type instanceof ReflectionNamedType &&
                    ! is_a($type->getName(), State::class, true);
            })
            ->mapWithKeys(fn (ReflectionProperty $prop) => [
                $prop->getName() => $properties[$prop->getName()] ?? null,
            ]);
    }

    public static function getEventParameters(ReflectionFunctionAbstract|Closure $method): array
    {
        return static::getParametersOfType(Event::class, $method);
    }

    public static function getStateParameters(ReflectionFunctionAbstract|Closure $method): array
    {
        return static::getParametersOfType(State::class, $method);
    }

    public static function applyAttributes(ReflectionFunctionAbstract|Closure $method, Hook $hook): Hook
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

    public static function getParametersOfType(string $type, ReflectionFunctionAbstract|Closure $method): array
    {
        $method = static::reflectFunction($method);

        if (empty($parameters = $method->getParameters())) {
            return [];
        }

        return array_filter(
            array: static::getParameterClassNames($parameters[0]),
            callback: fn (string $class_name) => is_a($class_name, $type, true)
        );
    }

    public static function getStatesFromIds(Event $target): array
    {
        $reflect = new ReflectionClass($target);

        // Get all possible attributes in a [attribute, property name] tuple

        $class_attributes = collect($reflect->getAttributes())
            ->filter(fn (ReflectionAttribute $attribute) => is_a($attribute->getName(), StateDiscoveryAttribute::class, true))
            ->map(fn (ReflectionAttribute $attribute) => $attribute->newInstance());

        $property_attributes = collect($reflect->getProperties(ReflectionProperty::IS_PUBLIC))
            ->flatMap(function (ReflectionProperty $property) {
                return collect($property->getAttributes())
                    ->filter(fn (ReflectionAttribute $attribute) => is_a($attribute->getName(), StateDiscoveryAttribute::class, true))
                    ->map(function (ReflectionAttribute $attribute) use ($property) {
                        $instance = $attribute->newInstance();

                        if ($instance instanceof ReflectsProperty) {
                            $instance->setReflection($property);
                        }

                        return $instance;
                    });
            });

        [$discovered, $deferred] = collect($class_attributes)
            ->merge($property_attributes)
            ->reduceSpread(function (Collection $discovered, Collection $deferred, StateDiscoveryAttribute $attribute) use ($target) {
                if ($attribute instanceof DependsOnDiscoveredState) {
                    $deferred->push($attribute);
                } else {
                    $discovered->push($attribute->discoverState($target));
                }

                return [$discovered, $deferred];
            }, new Collection(), new Collection());

        $deferred = $deferred
            ->map(fn (DependsOnDiscoveredState $attribute) => $attribute->setDiscoveredState($discovered))
            ->map(fn (StateDiscoveryAttribute $attribute) => $attribute->discoverState($target));

        // FIXME: Aliases

        return $discovered
            ->merge($deferred)
            ->filter()
            ->keyBy(fn (State $state) => $state::class)
            ->all();
    }

    protected static function reflectFunction(ReflectionFunctionAbstract|Closure $function): ReflectionFunctionAbstract
    {
        if ($function instanceof Closure) {
            return new ReflectionFunction($function);
        }

        return $function;
    }
}
