<?php

namespace Thunk\Verbs\Attributes\StateDiscovery;

use Attribute;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Thunk\Verbs\Event;
use Thunk\Verbs\Lifecycle\StateStore;
use Thunk\Verbs\State;

#[Attribute(Attribute::TARGET_CLASS)]
class AppliesToChildState implements DependsOnDiscoveredState, StateDiscoveryAttribute
{
    /** @var Collection<string, State> */
    protected Collection $discovered;

    public function __construct(
        public string $state_type,
        public string $parent_type,
        public string $id,
        public ?string $alias = null, // TODO
    ) {
        if (! is_a($this->state_type, State::class, true)) {
            throw new InvalidArgumentException('You must pass state class names to the "AppliesToChildState" attribute.');
        }

        if (! is_a($this->parent_type, State::class, true)) {
            throw new InvalidArgumentException('You must pass state class names to the "AppliesToChildState" attribute.');
        }
    }

    public function dependencies(): array
    {
        return [$this->parent_type];
    }

    public function setDiscoveredState(Collection $discovered): static
    {
        $this->discovered = $discovered;

        return $this;
    }

    public function discoverState(Event $event): State
    {
        $store = app(StateStore::class);

        $parent = $this->discovered->first(fn (State $state) => $state instanceof $this->parent_type);

        return $store->load($parent->{$this->id}, $this->state_type);
    }

    public function getAlias(): ?string
    {
        return $this->alias;
    }
}
