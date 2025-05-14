<?php

namespace Thunk\Verbs\Support;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionUnionType;
use Thunk\Verbs\Attributes\Autodiscovery\StateDiscoveryAttribute;
use Thunk\Verbs\Event;
use Thunk\Verbs\Lifecycle\StateManager;
use Thunk\Verbs\State;
use WeakMap;

class EventStateRegistry
{
    protected array $discovered_attributes = [];

    protected array $discovered_properties = [];

    protected WeakMap $discovered_states;

    public function __construct(
        protected StateManager $manager,
    ) {
        $this->discovered_states = new WeakMap;
    }

    public function reset()
    {
        $this->discovered_states = new WeakMap;
    }

    public function getStates(Event $event): StateCollection
    {
        return $this->discovered_states[$event] ??= $this->discoverStates($event);
    }

    protected function discoverStates(Event $event): StateCollection
    {
        dump('Discovering state: '.$event::class." ($event->id)");
        $discovered = new StateCollection;
        $deferred = new StateCollection;

        // If there are any properties that are states, we'll load them first
        $discovered->push(...$this->getProperties($event));

        foreach ($this->getAttributes($event) as $attribute) {
            // If there are state dependencies that the attribute relies on that we haven't already
            // loaded, then we'll have to defer it until all other dependencies are loaded. Otherwise,
            // we can load the state with what we already have.
            if (! $discovered->keys()->has($attribute->dependencies())) {
                $deferred->push($attribute);
            } else {
                $this->discoverAndPushState($attribute, $event, $discovered);
            }
        }

        // Once we've loaded everything else, try to discover any deferred attributes
        $deferred->each(fn (StateDiscoveryAttribute $attr) => $this->discoverAndPushState($attr, $event, $discovered));

        return $discovered;
    }

    /** @return Collection<string, State> */
    protected function discoverAndPushState(StateDiscoveryAttribute $attribute, Event $target, StateCollection $discovered): Collection
    {
        $states = Arr::wrap(
            $attribute
                ->setDiscoveredState($discovered)
                ->discoverState($target, $this->manager),
        );

        $discovered->push(...$states);

        if (count($states) > 0 && $alias = $attribute->getAlias()) {
            if (count($states) > 1) {
                throw new InvalidArgumentException('You cannot provide an alias for an array of states.');
            }

            $discovered->alias($alias, $states[0]);
        }

        return $discovered;
    }

    /** @return Collection<int, StateDiscoveryAttribute> */
    protected function getAttributes(Event $target): Collection
    {
        return $this->discovered_attributes[$target::class] ??= $this->findAllAttributes($target);
    }

    /** @return Collection<int, StateDiscoveryAttribute> */
    protected function findAllAttributes(Event $target): Collection
    {
        $reflect = new ReflectionClass($target);

        return $this->findClassAttributes($reflect)->merge($this->findPropertyAttributes($reflect));
    }

    /** @return Collection<int, StateDiscoveryAttribute> */
    protected function findClassAttributes(ReflectionClass $reflect): Collection
    {
        return collect($reflect->getAttributes())
            ->filter($this->isStateDiscoveryAttribute(...))
            ->map(fn (ReflectionAttribute $attribute) => $attribute->newInstance());
    }

    /** @return Collection<int, StateDiscoveryAttribute> */
    protected function findPropertyAttributes(ReflectionClass $reflect): Collection
    {
        return collect($reflect->getProperties(ReflectionProperty::IS_PUBLIC))
            ->flatMap(fn (ReflectionProperty $property) => collect($property->getAttributes())
                ->filter($this->isStateDiscoveryAttribute(...))
                ->map(fn (ReflectionAttribute $attribute) => $attribute->newInstance())
                ->map(fn (StateDiscoveryAttribute $attribute) => $attribute->setProperty($property)));
    }

    protected function isStateDiscoveryAttribute(ReflectionAttribute $attribute): bool
    {
        return is_a($attribute->getName(), StateDiscoveryAttribute::class, true);
    }

    /** @return Collection<int, State> */
    protected function getProperties(Event $target): Collection
    {
        return $this->discovered_properties[$target::class][$target->id] ??= $this->findAllProperties($target);
    }

    /** @return Collection<int, State> */
    protected function findAllProperties(Event $target): Collection
    {
        $reflect = new ReflectionClass($target);

        return collect($reflect->getProperties(ReflectionProperty::IS_PUBLIC))
            ->filter(function (ReflectionProperty $property) use ($target) {
                $property_type = $property->getType();

                if (
                    $property_type instanceof ReflectionNamedType
                    && $property_type->allowsNull()
                    && $property->getValue($target) === null
                ) {
                    return false;
                }

                $all_property_types = match ($property_type::class) {
                    ReflectionUnionType::class, ReflectionIntersectionType::class => $property_type->getTypes(),
                    default => [$property_type],
                };

                foreach ($all_property_types as $type) {
                    $name = $type?->getName();
                    if ($name && $this->isStateClass($name)) {
                        return true;
                    }
                }

                return false;
            })
            ->map(fn (ReflectionProperty $property) => $property->getValue($target))
            ->flatten();
    }

    protected function isStateClass(string $name): bool
    {
        return is_subclass_of($name, State::class)
            || $name === State::class
            || $name === StateCollection::class;
    }
}
