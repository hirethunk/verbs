<?php

use Thunk\Verbs\Attributes\Autodiscovery\AppliesToState;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;
use Thunk\Verbs\Facades\Verbs;
use Thunk\Verbs\State;

test('stateid attribute allows setting an alias for a state collection with a single state', function () {
    $event = StateAliasTestEvent::fire(bar: 1, baz: 2);

    Verbs::commit();

    $state_collection = $event->states();

    expect($state_collection->aliasNames())->not->toContain('bar')
        ->and($state_collection->aliasNames())->not->toContain('baz')
        ->and($state_collection->aliasNames())->toContain('foo')
        ->and($state_collection->aliasNames())->toContain('hello');
});

class StateAliasTestState extends State {}

#[AppliesToState(StateAliasTestState::class, id: 'baz', alias: 'hello')]
class StateAliasTestEvent extends Event
{
    public function __construct(
        #[StateId(StateAliasTestState::class, 'foo')]
        public int $bar,
        public int $baz,
    ) {}
}
