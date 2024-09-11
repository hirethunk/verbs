<?php

use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;
use Thunk\Verbs\State;

it('can load many states from an array of ids', function () {
    $id1 = snowflake_id();
    $id2 = snowflake_id();

    LoadManyStatesTestEvent::commit(
        state_id: $id1
    );

    LoadManyStatesTestEvent::commit(
        state_id: $id2
    );

    $state1 = LoadManyStatesTestState::load($id1);
    $state2 = LoadManyStatesTestState::load($id2);

    $states = LoadManyStatesTestState::load([$id1, $id2]);

    $this->assertEquals($state1, $states->first());
    $this->assertEquals($state2, $states->last());
});

it('can load many states from a collection of ids', function () {
    $id1 = snowflake_id();
    $id2 = snowflake_id();

    LoadManyStatesTestEvent::commit(
        state_id: $id1
    );

    LoadManyStatesTestEvent::commit(
        state_id: $id2
    );

    $state1 = LoadManyStatesTestState::load($id1);
    $state2 = LoadManyStatesTestState::load($id2);

    $states = LoadManyStatesTestState::load(collect([$id1, $id2]));

    $this->assertEquals($state1, $states->first());
    $this->assertEquals($state2, $states->last());
});

class LoadManyStatesTestState extends State
{
    public bool $acknowledged = false;
}

class LoadManyStatesTestEvent extends Event
{
    #[StateId(LoadManyStatesTestState::class)]
    public int $state_id;

    #[StateId(LoadManyStatesTestState::class, autofill: false)]
    public ?int $other_state_id = null;

    public function apply(LoadManyStatesTestState $state, LoadManyStatesTestState $other_state)
    {
        $state->acknowledged = true;
        $other_state->acknowledged = true;
    }
}
