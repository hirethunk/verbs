<?php

namespace Thunk\Verbs\Attributes\Autodiscovery;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use ReflectionProperty;
use Thunk\Verbs\Contracts\TracksState;
use Thunk\Verbs\Event;
use Thunk\Verbs\State;

abstract class StateDiscoveryAttribute
{
    public ?string $alias = null;

    protected ReflectionProperty $property;

    /** @var Collection<string, State> */
    protected Collection $discovered;

    abstract public function discoverState(Event $event, TracksState $manager): State|array;

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
        return $this->alias;
    }

    public function dependencies(): array
    {
        return [];
    }

    public function hasDependencies(): bool
    {
        return count($this->dependencies()) > 0;
    }

    protected function inferAliasFromVariableName(string $name)
    {
        return str_contains($name, '_')
            ? Str::beforeLast($name, '_id')
            : Str::beforeLast($name, 'Id');
    }
}
