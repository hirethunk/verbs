<?php

namespace Thunk\Verbs\Attributes\Autodiscovery;

use Attribute;
use InvalidArgumentException;
use Thunk\Verbs\Event;
use Thunk\Verbs\Lifecycle\StateRegistry;
use Thunk\Verbs\Lifecycle\StateStore;
use Thunk\Verbs\State;

#[Attribute(Attribute::TARGET_CLASS)]
class AppliesToState extends StateDiscoveryAttribute
{
    public function __construct(
        public string $state_type,
        public string $id,
        public ?string $alias = null, // TODO
    ) {
        if (! is_a($this->state_type, State::class, true)) {
            throw new InvalidArgumentException('You must pass state class names to the "AppliesToState" attribute.');
        }
    }

    public function discoverState(Event $event, StateRegistry $registry): State
    {
        return $registry->load($event->{$this->id}, $this->state_type);
    }

    public function getAlias(): ?string
    {
        return $this->alias;
    }
}
