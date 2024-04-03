<?php

use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;
use Thunk\Verbs\Facades\Verbs;
use Thunk\Verbs\State;

class TestState extends State
{
}

class TestEvent extends Event
{
    #[StateId(TestState::class, 'foo')]
    public int $bar;
}

it('stateid attribute allows setting an alias for a state collection with a single state', function () {
    $event = TestEvent::fire(bar: 1);

    Verbs::commit();

    $state_collection = $event->states();

    expect($state_collection->aliasNames())->toContain('bar'); // the prop is the alias
    expect($state_collection->aliasNames())->toContain('foo'); // the string is not
});
