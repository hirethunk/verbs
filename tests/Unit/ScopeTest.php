<?php

use Thunk\Verbs\Contracts\StoresEvents;
use Thunk\Verbs\Contracts\StoresSnapshots;
use Thunk\Verbs\Exceptions\StateNotFoundException;
use Thunk\Verbs\Facades\Verbs;
use Thunk\Verbs\Lifecycle\MetadataManager;
use Thunk\Verbs\State;
use Thunk\Verbs\Testing\EventStoreFake;
use Thunk\Verbs\Testing\SnapshotStoreFake;

beforeEach(function () {
    app()->instance(StoresSnapshots::class, new SnapshotStoreFake);
    app()->instance(StoresEvents::class, new EventStoreFake(app(MetadataManager::class)));
});

test('loadOrFail triggers an exception if state does not exist', function () {
    ScopeTestState::loadOrFail(1);
})->throws(StateNotFoundException::class);

test('load does not trigger an exception if state does not exist', function () {
    expect(ScopeTestState::load(1))
        ->toBeInstanceOf(ScopeTestState::class)
        ->last_event_id->toBeNull();
});

test('snapshots are not stored for states that have no events', function () {
    ScopeTestState::load(1);
    Verbs::commit();

    app(StoresSnapshots::class)->assertNothingWritten();
});

test('it calls the state constructor on make', function () {
    $state = ScopeTestState::make();
    expect($state->constructed)->toBeTrue();
});

class ScopeTestState extends State
{
    public bool $constructed = false;

    public function __construct()
    {
        parent::__construct();
        $this->constructed = true;
    }
}
