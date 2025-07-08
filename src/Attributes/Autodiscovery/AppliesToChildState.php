<?php

namespace Thunk\Verbs\Attributes\Autodiscovery;

use Attribute;
use InvalidArgumentException;
use Thunk\Verbs\Event;
use Thunk\Verbs\Lifecycle\StateManager;
use Thunk\Verbs\State;

#[Attribute(Attribute::TARGET_CLASS)]
class AppliesToChildState extends StateDiscoveryAttribute
{
    public function __construct(
        public string $state_type,
        public string $parent_type,
        public string $id,
        public ?string $alias = null,
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

    public function discoverState(Event $event, StateManager $manager): State
    {
        $parent = $this->discovered->first(fn (State $state) => $state instanceof $this->parent_type);

        return $manager->load($this->state_type, $parent->{$this->id});
    }
}
