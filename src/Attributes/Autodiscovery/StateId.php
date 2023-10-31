<?php

namespace Thunk\Verbs\Attributes\Autodiscovery;

use Attribute;
use InvalidArgumentException;
use Thunk\Verbs\Event;
use Thunk\Verbs\Lifecycle\StateStore;
use Thunk\Verbs\State;

#[Attribute(Attribute::TARGET_PROPERTY)]
class StateId extends StateDiscoveryAttribute
{
    public function __construct(
        public string $state_type,
        public ?string $alias = null,
    ) {
        if (! is_a($this->state_type, State::class, true)) {
            throw new InvalidArgumentException('You must pass state class names to the "Identifies" attribute.');
        }
    }

    public function discoverState(Event $event): State
    {
        return app(StateStore::class)->load($this->property->getValue($event), $this->state_type);
    }
}
