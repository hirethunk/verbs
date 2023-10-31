<?php

namespace Thunk\Verbs\Attributes\Autodiscovery;

use Illuminate\Support\Collection;
use ReflectionProperty;
use Thunk\Verbs\Event;
use Thunk\Verbs\Lifecycle\StateRegistry;
use Thunk\Verbs\State;

abstract class StateDiscoveryAttribute
{
    protected ReflectionProperty $property;

    /** @var Collection<string, State> */
    protected Collection $discovered;

    abstract public function discoverState(Event $event, StateRegistry $registry): State;

    public function setProperty(ReflectionProperty $property): static
    {
        $this->property = $property;

        return $this;
    }

    public function setDiscoveredState(Collection $discovered): static
    {
        $this->discovered = $discovered;

        return $this;
    }

    public function getAlias(): ?string
    {
        return property_exists($this, 'alias') ? $this->alias : null;
    }

    public function dependencies(): array
    {
        return [];
    }

    public function hasDependencies(): bool
    {
        return count($this->dependencies()) > 0;
    }
}
