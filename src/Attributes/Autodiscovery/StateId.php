<?php

namespace Thunk\Verbs\Attributes\Autodiscovery;

use Attribute;
use Glhd\Bits\Snowflake;
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
        public bool $autofill = true,
    ) {
        if (! is_a($this->state_type, State::class, true)) {
            throw new InvalidArgumentException('You must pass state class names to the "Identifies" attribute.');
        }
    }

    public function discoverState(Event $event): State
    {
        $value = $this->property->getValue($event);

        // If the ID hasn't been set yet, we'll automatically set one
        if ($value === null && $this->autofill) {
            $value = Snowflake::make()->id();
            $this->property->setValue($event, $value);
        }

        return app(StateStore::class)->load($value, $this->state_type);
    }
}
