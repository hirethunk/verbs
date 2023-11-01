<?php

namespace Thunk\Verbs\Attributes\Autodiscovery;

use Attribute;
use InvalidArgumentException;
use Thunk\Verbs\Event;
use Thunk\Verbs\Lifecycle\StateRegistry;
use Thunk\Verbs\State;

#[Attribute(Attribute::TARGET_CLASS)]
class AppliesToSingletonState extends StateDiscoveryAttribute
{
    public function __construct(
        public string $state_type,
        public ?string $alias = null,
    ) {
        if (! is_a($this->state_type, State::class, true)) {
            throw new InvalidArgumentException('You must pass state class names to the "AppliesToState" attribute.');
        }
    }

    public function discoverState(Event $event, StateRegistry $registry): State
    {
        return $registry->singleton($this->state_type);
    }
}
