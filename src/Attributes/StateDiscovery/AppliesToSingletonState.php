<?php

namespace Thunk\Verbs\Attributes\StateDiscovery;

use Attribute;
use InvalidArgumentException;
use Thunk\Verbs\Event;
use Thunk\Verbs\Lifecycle\StateStore;
use Thunk\Verbs\State;

#[Attribute(Attribute::TARGET_CLASS)]
class AppliesToSingletonState implements StateDiscoveryAttribute
{
    public function __construct(
        public string $state_type,
        public ?string $alias = null, // TODO
    ) {
        if (! is_a($this->state_type, State::class, true)) {
            throw new InvalidArgumentException('You must pass state class names to the "AppliesToState" attribute.');
        }
    }

    public function discoverState(Event $event): State
    {
        // FIXME: dont use "0"
        return app(StateStore::class)->load(0, $this->state_type);
    }

    public function getAlias(): ?string
    {
        return $this->alias;
    }
}
