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

    public function discoverState(Event $event, StateManager $manager): State|array
    {
        $id = $this->property->getValue($event);
        $property_name = $this->property->getName();
        $meta = $event->metadata();

        if (! is_array($id)) {
            $this->alias ??= $this->inferAliasFromVariableName($property_name);
        }

        // If the ID hasn't been set yet, we'll automatically set one
        if ($id === null && $this->autofill) {
            $id = snowflake_id();
            $this->property->setValue($event, $id);

            $autofilled = $meta->get('autofilled', []);
            $autofilled[$property_name] = true;
            $meta->put('autofilled', $autofilled);

            return $manager->make($id, $this->state_type);
        }

        // If we autofilled the value when it first fired, then we know this is the
        // first event for that given state, and we don't need to try to load it
        if ($meta->get("autofilled.{$property_name}", false)) {
            return $manager->make($id, $this->state_type);
        }

        return array_map(fn ($id) => $manager->load($id, $this->state_type), Arr::wrap($id));
    }
}
