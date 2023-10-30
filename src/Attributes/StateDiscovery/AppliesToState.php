<?php

namespace Thunk\Verbs\Attributes\StateDiscovery;

use Attribute;
use InvalidArgumentException;
use Thunk\Verbs\Event;
use Thunk\Verbs\Lifecycle\StateStore;
use Thunk\Verbs\State;

#[Attribute(Attribute::TARGET_CLASS)]
class AppliesToState implements StateDiscoveryAttribute
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

    public function discoverState(Event $event): State
    {
        return app(StateStore::class)->load($event->{$this->id}, $this->state_type);
    }
}
