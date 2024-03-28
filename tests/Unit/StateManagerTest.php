<?php

use Thunk\Verbs\Exceptions\StateNotFoundException;
use Thunk\Verbs\Lifecycle\Dispatcher;
use Thunk\Verbs\Lifecycle\MetadataManager;
use Thunk\Verbs\Lifecycle\StateManager;
use Thunk\Verbs\State;
use Thunk\Verbs\Testing\EventStoreFake;
use Thunk\Verbs\Testing\SnapshotStoreFake;

beforeEach(fn () => app()->instance(StateManager::class, new StateManager(
    dispatcher: app(Dispatcher::class),
    snapshots: new SnapshotStoreFake(),
    events: new EventStoreFake(app(MetadataManager::class)),
)));

test('loadOrFail triggers an exception if state does not exist', function () {
    StateManagerTestState::loadOrFail(1);
})->throws(StateNotFoundException::class);

test('load does not trigger an exception if state does not exist', function () {
    expect(StateManagerTestState::load(1))
        ->toBeInstanceOf(StateManagerTestState::class)
        ->last_event_id->toBeNull();
});

class StateManagerTestState extends State
{
}
