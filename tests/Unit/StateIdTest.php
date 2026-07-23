<?php

use Glhd\Bits\Snowflake;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;
use Thunk\Verbs\Facades\Verbs;
use Thunk\Verbs\State;

it('can find the state ID property', function () {
    $id = snowflake_id();

    StateIdTestEvent::commit(
        state_id: $id
    );

    $state = StateIdTestState::load($id);

    $this->assertTrue($state->acknowledged);
});

it('will autofill snowflake by default when state_id is nullable', function () {
    $event = AutofillTestEvent::fire(
        // pass no state_id
    );

    Verbs::commit();

    // autofilled id is snowflake
    expect(
        strlen((string) $event->id)
    )->toBe(
        strlen((string) snowflake_id())
    );

    expect($event->id)->tobeInt();

    expect($event->states())->toHaveCount(1);
    expect($event->states()->get('foo')->acknowledged)->toBe(true);
});

it('can disable autofill for a state ID property and still load state if ID is supplied', function () {
    $id = snowflake_id();
    $other_id = snowflake_id();

    $event = StateIdTestEvent::fire(
        state_id: $id,
        other_state_id: $other_id,
    );

    Verbs::commit();

    expect($event->states())
        ->toHaveCount(2)
        ->get(0)->id->toBe($id)
        ->get(0)->acknowledged->toBe(true)
        ->get(1)->id->toBe($other_id)
        ->get(1)->acknowledged->toBe(true);
});

it('can disable autofill for a state ID property allowing it to be set to null', function () {
    $id = snowflake_id();

    $event = StateIdTestEvent::fire(
        state_id: $id,
        other_state_id: null,
    );

    Verbs::commit();

    expect($event->states())
        ->toHaveCount(1)
        ->get(0)->id->toBe($id)
        ->get(0)->acknowledged->toBe(true);
});

class StateIdTestState extends State
{
    public bool $acknowledged = false;
}

class StateIdTestEvent extends Event
{
    #[StateId(StateIdTestState::class)]
    public int $state_id;

    #[StateId(StateIdTestState::class, autofill: false)]
    public ?int $other_state_id = null;

    public function apply(StateIdTestState $state, StateIdTestState $other_state)
    {
        $state->acknowledged = true;
        $other_state->acknowledged = true;
    }
}
class AutofillTestEvent extends Event
{
    #[StateId(StateIdTestState::class, 'foo')]
    public ?int $state_id = null;

    public function apply(StateIdTestState $state)
    {
        $state->acknowledged = true;
    }
}
