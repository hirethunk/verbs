<?php

namespace Thunk\Verbs\Attributes\Autodiscovery;

use Attribute;
use Illuminate\Support\Arr;
use InvalidArgumentException;
use Thunk\Verbs\Event;
use Thunk\Verbs\Lifecycle\StateManager;
use Thunk\Verbs\State;

#[Attribute(Attribute::TARGET_PROPERTY)]
class StateId extends StateDiscoveryAttribute
{
    /** @param  class-string<State>  $state_type */
    public function __construct(
        public string $state_type,
        public ?string $alias = null,
        public bool $autofill = true,
    ) {
        if (! is_a($this->state_type, State::class, true)) {
            throw new InvalidArgumentException('You must pass state class names to the "StateId" attribute.');
        }
    }

    public function discoverState(Event $event, StateManager $manager): array
    {
        $id = $this->property->getValue($event);

        // If the ID hasn't been set yet, we'll automatically set one
        if ($id === null && $this->autofill) {
            $id = snowflake_id();
            $this->property->setValue($event, $id);
        }

        if (! is_array($id)) {
            $this->alias = $this->inferAliasFromVariableName($this->property->getName());
        }

        return collect(Arr::wrap($id))
            ->map(fn ($id) => $manager->load($id, $this->state_type))
            ->all();
    }
}
