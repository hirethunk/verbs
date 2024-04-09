<?php

use Thunk\Verbs\Contracts\StoresEvents;
use Thunk\Verbs\Contracts\StoresSnapshots;
use Thunk\Verbs\Exceptions\StateNotFoundException;
use Thunk\Verbs\Facades\Verbs;
use Thunk\Verbs\Lifecycle\MetadataManager;
use Thunk\Verbs\Models\VerbSnapshot;
use Thunk\Verbs\State;
use Thunk\Verbs\Testing\EventStoreFake;
use Thunk\Verbs\Testing\SnapshotStoreFake;

beforeEach(function () {
    app()->instance(StoresSnapshots::class, new SnapshotStoreFake());
    app()->instance(StoresEvents::class, new EventStoreFake(app(MetadataManager::class)));
}
);

test('loadOrFail triggers an exception if state does not exist', function () {
    StateManagerTestState::loadOrFail(1);
})->throws(StateNotFoundException::class);

test('load does not trigger an exception if state does not exist', function () {
    expect(StateManagerTestState::load(1))
        ->toBeInstanceOf(StateManagerTestState::class)
        ->last_event_id->toBeNull();
});

test('snapshots are not stored for states that have no events', function () {
    StateManagerTestState::load(1);
    Verbs::commit();

    app(StoresSnapshots::class)->assertNothingWritten();
});

class StateManagerTestState extends State
{
}
