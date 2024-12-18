<?php

use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Str;
use Thunk\Verbs\Event;
use Thunk\Verbs\Lifecycle\StateManager;
use Thunk\Verbs\State;
use Thunk\Verbs\Support\IdManager;

use function Pest\Laravel\artisan;

beforeEach(function () {
    // This is necessary because our Orchestra setup migrates the database
    // before our tests run, so we need to re-run our migrations
    config()->set('verbs.id_type', 'uuid');
    app()->instance(IdManager::class, new IdManager('uuid'));
    Facade::clearResolvedInstance(IdManager::class);
    artisan('migrate:fresh');
});

afterAll(function () {
    // This just resets the migrations back to how the were before this test suite
    config()->set('verbs.id_type', 'snowflake');
    app()->instance(IdManager::class, new IdManager('snowflake'));
    Facade::clearResolvedInstance(IdManager::class);
    artisan('migrate:fresh');
});

it('supports using uuids as state ids', function () {
    $uuid = (string) Str::orderedUuid();

    $state = UuidState::load($uuid);

    UuidEvent::commit(
        state: $state,
    );

    expect($state)
        ->id->toBe($uuid)
        ->event_was_applied->toBeTrue();
});

it('loads states correctly using uuids when the snapshots table has been removed', function () {
    $uuid = (string) Str::orderedUuid();

    $state = UuidState::load($uuid);

    UuidEvent::commit(
        state: $state,
    );

    app(StateManager::class)->reset(include_storage: true);

    $state = UuidState::load($uuid);

    expect($state)
        ->id->toBe($uuid)
        ->event_was_applied->toBeTrue();
});

class UuidState extends State
{
    public bool $event_was_applied = false;
}

class UuidEvent extends Event
{
    public UuidState $state;

    public function apply()
    {
        $this->state->event_was_applied = true;
    }
}
