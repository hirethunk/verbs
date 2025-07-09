<?php

use Thunk\Verbs\Event;
use Thunk\Verbs\Models\VerbEvent;
use Thunk\Verbs\Models\VerbSnapshot;
use Thunk\Verbs\Models\VerbStateEvent;
use Thunk\Verbs\State;
use Thunk\Verbs\State\ReconstitutionQuery;

it('returns data as expected', function () {
    // Six fake events
    VerbEvent::truncate();
    VerbEvent::insert(['id' => 1, 'type' => ReconstitutionQueryTestEvent::class, 'data' => '{}', 'metadata' => '{}']);
    VerbEvent::insert(['id' => 2, 'type' => ReconstitutionQueryTestEvent::class, 'data' => '{}', 'metadata' => '{}']);
    VerbEvent::insert(['id' => 3, 'type' => ReconstitutionQueryTestEvent::class, 'data' => '{}', 'metadata' => '{}']);
    VerbEvent::insert(['id' => 4, 'type' => ReconstitutionQueryTestEvent::class, 'data' => '{}', 'metadata' => '{}']);
    VerbEvent::insert(['id' => 5, 'type' => ReconstitutionQueryTestEvent::class, 'data' => '{}', 'metadata' => '{}']);
    VerbEvent::insert(['id' => 6, 'type' => ReconstitutionQueryTestEvent::class, 'data' => '{}', 'metadata' => '{}']);

    // Attach events to different states
    VerbStateEvent::truncate();
    VerbStateEvent::insert(['id' => 1, 'event_id' => 1, 'state_id' => 1, 'state_type' => ReconstitutionQueryTestState::class]);
    VerbStateEvent::insert(['id' => 2, 'event_id' => 2, 'state_id' => 1, 'state_type' => ReconstitutionQueryTestState::class]);
    VerbStateEvent::insert(['id' => 3, 'event_id' => 2, 'state_id' => 2, 'state_type' => ReconstitutionQueryTestState::class]);
    VerbStateEvent::insert(['id' => 4, 'event_id' => 3, 'state_id' => 2, 'state_type' => ReconstitutionQueryTestState::class]);
    VerbStateEvent::insert(['id' => 5, 'event_id' => 4, 'state_id' => 1, 'state_type' => ReconstitutionQueryTestState::class]);
    VerbStateEvent::insert(['id' => 6, 'event_id' => 5, 'state_id' => 1, 'state_type' => ReconstitutionQueryTestState::class]);
    VerbStateEvent::insert(['id' => 7, 'event_id' => 5, 'state_id' => 2, 'state_type' => ReconstitutionQueryTestState::class]);
    VerbStateEvent::insert(['id' => 8, 'event_id' => 6, 'state_id' => 2, 'state_type' => ReconstitutionQueryTestState::class]);

    // CASE: All events have snapshots (same event ID)
    VerbSnapshot::truncate();
    VerbSnapshot::insert(['id' => 1, 'state_id' => 1, 'type' => ReconstitutionQueryTestState::class, 'data' => '{}', 'last_event_id' => 2]);
    VerbSnapshot::insert(['id' => 2, 'state_id' => 2, 'type' => ReconstitutionQueryTestState::class, 'data' => '{}', 'last_event_id' => 2]);

    $results = (new ReconstitutionQuery(ReconstitutionQueryTestState::class, 2));

    expect($results->states())->toHaveCount(2);

    expect($results->earliestEventId())->toBe(2);

    expect($results->states()->first())
        ->toBeInstanceOf(ReconstitutionQueryTestState::class)
        ->id->toBe('1')
        ->last_event_id->toBe(2);

    expect($results->states()->last())
        ->toBeInstanceOf(ReconstitutionQueryTestState::class)
        ->id->toBe('2')
        ->last_event_id->toBe(2);

    // CASE: All events have snapshots (different event IDs, queried state is earlier)
    VerbSnapshot::truncate();
    VerbSnapshot::insert(['id' => 1, 'state_id' => 1, 'type' => ReconstitutionQueryTestState::class, 'data' => '{}', 'last_event_id' => 4]);
    VerbSnapshot::insert(['id' => 2, 'state_id' => 2, 'type' => ReconstitutionQueryTestState::class, 'data' => '{}', 'last_event_id' => 2]);

    $results = (new ReconstitutionQuery(ReconstitutionQueryTestState::class, 2));

    expect($results->states())->toHaveCount(2);

    expect($results->earliestEventId())->toBe(2);

    expect($results->states()->first())
        ->toBeInstanceOf(ReconstitutionQueryTestState::class)
        ->id->toBe('1')
        ->last_event_id->toBe(4);

    expect($results->states()->last())
        ->toBeInstanceOf(ReconstitutionQueryTestState::class)
        ->id->toBe('2')
        ->last_event_id->toBe(2);

    // CASE: All events have snapshots (different event IDs, queried state is later)
    VerbSnapshot::truncate();
    VerbSnapshot::insert(['id' => 1, 'state_id' => 1, 'type' => ReconstitutionQueryTestState::class, 'data' => '{}', 'last_event_id' => 1]);
    VerbSnapshot::insert(['id' => 2, 'state_id' => 2, 'type' => ReconstitutionQueryTestState::class, 'data' => '{}', 'last_event_id' => 2]);

    $results = (new ReconstitutionQuery(ReconstitutionQueryTestState::class, 2));

    expect($results->states())->toHaveCount(2);

    expect($results->earliestEventId())->toBe(1);

    expect($results->states()->first())
        ->toBeInstanceOf(ReconstitutionQueryTestState::class)
        ->id->toBe('1')
        ->last_event_id->toBe(1);

    expect($results->states()->last())
        ->toBeInstanceOf(ReconstitutionQueryTestState::class)
        ->id->toBe('2')
        ->last_event_id->toBe(2);

    // CASE: One event has a snapshot, the other doesn't (queried state has snapshot)
    VerbSnapshot::truncate();
    VerbSnapshot::insert(['id' => 2, 'state_id' => 2, 'type' => ReconstitutionQueryTestState::class, 'data' => '{}', 'last_event_id' => 3]);

    $results = (new ReconstitutionQuery(ReconstitutionQueryTestState::class, 2));

    expect($results->states())->toHaveCount(2);

    expect($results->earliestEventId())->toBe(0);

    expect($results->states()->first())
        ->toBeInstanceOf(ReconstitutionQueryTestState::class)
        ->id->toBe('1')
        ->last_event_id->toBe(0);

    expect($results->states()->last())
        ->toBeInstanceOf(ReconstitutionQueryTestState::class)
        ->id->toBe('2')
        ->last_event_id->toBe(3);

    // CASE: One event has a snapshot, the other doesn't (non-queried state has snapshot)
    VerbSnapshot::truncate();
    VerbSnapshot::insert(['id' => 1, 'state_id' => 1, 'type' => ReconstitutionQueryTestState::class, 'data' => '{}', 'last_event_id' => 2]);

    $results = (new ReconstitutionQuery(ReconstitutionQueryTestState::class, 2));

    expect($results->states())->toHaveCount(2);

    expect($results->earliestEventId())->toBe(0);

    expect($results->states()->first())
        ->toBeInstanceOf(ReconstitutionQueryTestState::class)
        ->id->toBe('1')
        ->last_event_id->toBe(2);

    expect($results->states()->last())
        ->toBeInstanceOf(ReconstitutionQueryTestState::class)
        ->id->toBe('2')
        ->last_event_id->toBe(0);

    // CASE: Neither event has a snapshot
    VerbSnapshot::truncate();

    $results = (new ReconstitutionQuery(ReconstitutionQueryTestState::class, 2));

    expect($results->states())->toHaveCount(2);

    expect($results->earliestEventId())->toBe(0);

    expect($results->states()->first())
        ->toBeInstanceOf(ReconstitutionQueryTestState::class)
        ->id->toBe('1')
        ->last_event_id->toBe(0);

    expect($results->states()->last())
        ->toBeInstanceOf(ReconstitutionQueryTestState::class)
        ->id->toBe('2')
        ->last_event_id->toBe(0);
});

class ReconstitutionQueryTestState extends State {}
class ReconstitutionQueryTestEvent extends Event {}
