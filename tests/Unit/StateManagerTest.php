<?php

use Thunk\Verbs\Exceptions\StateNotFoundException;
use Thunk\Verbs\Facades\Verbs;
use Thunk\Verbs\Lifecycle\BrokerStore;
use Thunk\Verbs\State;

test('loadOrFail triggers an exception if state does not exist', function () {
    Verbs::fake();

    StateManagerTestState::loadOrFail(1);
})->throws(StateNotFoundException::class);

test('load does not trigger an exception if state does not exist', function () {
    Verbs::fake();

    expect(StateManagerTestState::load(1))
        ->toBeInstanceOf(StateManagerTestState::class)
        ->last_event_id->toBeNull();
});

test('snapshots are not stored for states that have no events', function () {
    Verbs::fake();

    StateManagerTestState::load(1);
    Verbs::commit();

    app(BrokerStore::class)->current()->snapshot_store->assertNothingWritten();
});

test('it calls the state constructor on make', function () {
    Verbs::fake();

    $state = StateManagerTestState::make();
    expect($state->constructed)->toBeTrue();
});

class StateManagerTestState extends State
{
    public bool $constructed = false;

    public function __construct()
    {
        parent::__construct();
        $this->constructed = true;
    }
}
