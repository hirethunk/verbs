<?php

namespace Thunk\Verbs\Attributes\StateDiscovery;

use Attribute;
use InvalidArgumentException;
use ReflectionProperty;
use Thunk\Verbs\Event;
use Thunk\Verbs\Lifecycle\StateStore;
use Thunk\Verbs\State;

#[Attribute(Attribute::TARGET_PROPERTY)]
class StateId implements ReflectsProperty, StateDiscoveryAttribute
{
    protected ReflectionProperty $property;

    public function __construct(
        public string $state_type,
        public ?string $alias = null,
    ) {
        if (! is_a($this->state_type, State::class, true)) {
            throw new InvalidArgumentException('You must pass state class names to the "Identifies" attribute.');
        }
    }

    public function setReflection(ReflectionProperty $reflection)
    {
        $this->property = $reflection;
    }

    public function discoverState(Event $event): State
    {
        return app(StateStore::class)->load($this->property->getValue($event), $this->state_type);
    }

    public function getAlias(): ?string
    {
        return $this->alias;
    }
}
