<?php

namespace Thunk\Verbs\Support;

use Illuminate\Support\Collection;
use OutOfBoundsException;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionProperty;
use Thunk\Verbs\Attributes\Autodiscovery\StateDiscoveryAttribute;
use Thunk\Verbs\Event;
use Thunk\Verbs\State;

class EventStateRegistry
{
    public function __construct(
        public array $attributes = [],
    ) {
    }

    public function getStates(Event $event): StateCollection
    {
        $discovered = new StateCollection();
        $deferred = new StateCollection();

        foreach ($this->findAttributes($event) as $attribute) {
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
        $state = $attribute
            ->setDiscoveredState($discovered)
            ->discoverState($target);

        if ($discovered->has($state::class)) {
            throw new OutOfBoundsException('An event can only be associated with a single instance of any given state.');
        }

        $discovered->put($state::class, $state);
        $discovered->alias($attribute->getAlias(), $state::class);

        return $discovered;
    }

    /** @return Collection<int, StateDiscoveryAttribute> */
    protected function findAttributes(Event $target): Collection
    {
        if (! isset($this->attributes[$target::class])) {
            $reflect = new ReflectionClass($target);

            $this->attributes[$target::class] = collect($this->findClassAttributes($reflect))
                ->merge($this->findPropertyAttributes($reflect));
        }

        return $this->attributes[$target::class];
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
}
