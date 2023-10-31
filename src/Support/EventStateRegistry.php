<?php

namespace Thunk\Verbs\Support;

use Illuminate\Support\Collection;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionProperty;
use Thunk\Verbs\Attributes\Autodiscovery\StateDiscoveryAttribute;
use Thunk\Verbs\Event;
use Thunk\Verbs\State;

class EventStateRegistry
{
    public function __construct()
    {

    }

    public static function getStatesFromIds(Event $target): array
    {
        $reflect = new ReflectionClass($target);

        $class_attributes = collect($reflect->getAttributes())
            ->filter(fn (ReflectionAttribute $attribute) => is_a($attribute->getName(), StateDiscoveryAttribute::class, true))
            ->map(fn (ReflectionAttribute $attribute) => $attribute->newInstance());

        $property_attributes = collect($reflect->getProperties(ReflectionProperty::IS_PUBLIC))
            ->flatMap(function (ReflectionProperty $property) {
                return collect($property->getAttributes())
                    ->filter(fn (ReflectionAttribute $attribute) => is_a($attribute->getName(), StateDiscoveryAttribute::class, true))
                    ->map(function (ReflectionAttribute $attribute) use ($property) {
                        $instance = $attribute->newInstance();
                        $instance->setProperty($property);

                        return $instance;
                    });
            });

        [$discovered, $deferred] = collect($class_attributes)
            ->merge($property_attributes)
            ->reduceSpread(function (Collection $discovered, Collection $deferred, StateDiscoveryAttribute $attribute) use ($target) {

                if ($attribute->hasDependencies()) {
                    $deferred->push($attribute);
                } else {
                    dump($attribute);
                    $discovered->push($attribute->discoverState($target));
                }

                return [$discovered, $deferred];
            }, new Collection(), new Collection());

        $deferred = $deferred
            ->map(fn (StateDiscoveryAttribute $attribute) => $attribute->setDiscoveredState($discovered))
            ->map(fn (StateDiscoveryAttribute $attribute) => $attribute->discoverState($target));

        // FIXME: Aliases

        return $discovered
            ->merge($deferred)
            ->filter()
            ->keyBy(fn (State $state) => $state::class)
            ->all();
    }
}
