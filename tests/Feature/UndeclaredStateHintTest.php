<?php

use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;
use Thunk\Verbs\Exceptions\CannotResolveParameter;
use Thunk\Verbs\SingletonState;
use Thunk\Verbs\State;

/*
 * A hook may only type-hint states the event actually fires on. The old
 * container fallback constructed a fresh instance instead: never associated
 * with the event (so never event-sourced), and for singletons a guaranteed
 * identity collision on the next fire.
 */

it('fails loudly when a hook type-hints a singleton the event does not fire on', function () {
    UndeclaredSingletonHintEvent::fire();
})->throws(CannotResolveParameter::class, 'does not fire on that state');

it('fails loudly when a hook type-hints a keyed state the event does not fire on', function () {
    UndeclaredKeyedHintEvent::fire(other_id: snowflake_id());
})->throws(CannotResolveParameter::class, UndeclaredHintState::class);

it('still resolves states the event fires on', function () {
    $event = DeclaredHintEvent::fire(state_id: snowflake_id());

    expect($event->state(UndeclaredHintState::class)->count)->toBe(1);
});

it('resolves an optional hint for a state the event does not fire on to its default', function () {
    $event = OptionalUndeclaredHintEvent::fire(other_id: snowflake_id());

    expect($event->received)->toHaveCount(2)
        ->and($event->received['defaulted'])->toBeNull()
        ->and($event->received['nullable'])->toBeNull();
});

class UndeclaredHintSingleton extends SingletonState
{
    public int $count = 0;
}

class UndeclaredHintState extends State
{
    public int $count = 0;
}

class UndeclaredHintOtherState extends State {}

class UndeclaredSingletonHintEvent extends Event
{
    public function apply(UndeclaredHintSingleton $singleton): void
    {
        $singleton->count++;
    }
}

class UndeclaredKeyedHintEvent extends Event
{
    #[StateId(UndeclaredHintOtherState::class)]
    public int $other_id;

    public function apply(UndeclaredHintState $state): void
    {
        $state->count++;
    }
}

class OptionalUndeclaredHintEvent extends Event
{
    #[StateId(UndeclaredHintOtherState::class)]
    public int $other_id;

    public array $received = [];

    public function applyDefaulted(?UndeclaredHintState $state = null): void
    {
        $this->received['defaulted'] = $state;
    }

    public function applyNullable(?UndeclaredHintState $state): void
    {
        $this->received['nullable'] = $state;
    }
}

class DeclaredHintEvent extends Event
{
    #[StateId(UndeclaredHintState::class)]
    public int $state_id;

    public function apply(UndeclaredHintState $state): void
    {
        $state->count++;
    }
}
