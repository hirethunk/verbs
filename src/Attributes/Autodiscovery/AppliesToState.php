<?php

namespace Thunk\Verbs\Attributes\Autodiscovery;

use Attribute;
use Glhd\Bits\Snowflake;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Thunk\Verbs\Event;
use Thunk\Verbs\Lifecycle\StateManager;
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

        $this->id ??= Str::of($this->state_type)->classBasename()->beforeLast('State')->kebab()->append('_id');
    }

    public function discoverState(Event $event, StateManager $manager): State
    {
        $value = $event->{$this->id};

        // If the ID hasn't been set yet, we'll automatically set one
        if ($value === null && $this->autofill) {
            $value = Snowflake::make()->id();
            $event->{$this->id} = $value;
        }

        return $manager->load($value, $this->state_type);
    }

    public function getAlias(): ?string
    {
        return $this->alias;
    }
}
