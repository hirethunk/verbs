<?php

namespace Thunk\Verbs\Attributes\Autodiscovery;

use Attribute;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Thunk\Verbs\Event;
use Thunk\Verbs\Lifecycle\StateManager;
use Thunk\Verbs\SingletonState;
use Thunk\Verbs\State;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class AppliesToState extends StateDiscoveryAttribute
{
    public function __construct(
        public string $state_type,
        public ?string $id = null,
        public ?string $alias = null,
        public bool $autofill = true,
    ) {
        if (! is_a($this->state_type, State::class, true)) {
            throw new InvalidArgumentException('You must pass state class names to the "AppliesToState" attribute.');
        }
    }

    public function discoverState(Event $event, StateManager $manager): State|array
    {
        if (is_subclass_of($this->state_type, SingletonState::class)) {
            return $this->state_type::singleton();
        }

        $property = $this->getStateIdProperty($event);
        $id = $event->{$property};

        if (! is_array($id)) {
            $this->alias ??= $this->inferAliasFromVariableName($property);
        }

        // If the ID hasn't been set yet, we'll automatically set one
        if ($id === null && $this->autofill) {
            $id = snowflake_id();
            $event->{$property} = $id;

            return $manager->make($id, $this->state_type);
        }

        // TODO: Check type of data

        return collect(Arr::wrap($id))
            ->map(fn ($id) => $manager->load($id, $this->state_type))
            ->all();
    }

    protected function getStateIdProperty(Event $event): string
    {
        if ($this->id) {
            return $this->id;
        }

        $prefix = Str::of($this->state_type)->classBasename()->beforeLast('State')->snake();

        if (property_exists($event, $singular = "{$prefix}_id")) {
            return $singular;
        }

        if (property_exists($event, $plural = "{$prefix}_ids")) {
            return $plural;
        }

        throw new InvalidArgumentException("No ID property provided AppliesToState for {$this->state_type}");
    }
}
