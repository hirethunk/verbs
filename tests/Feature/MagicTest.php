<?php

use Thunk\Verbs\Models\VerbEvent;
use Thunk\Verbs\Models\VerbSnapshot;
use Thunk\Verbs\Models\VerbStateEvent;
use Thunk\Verbs\State\Magic;

it('is magic', function () {
    // Six fake events
    VerbEvent::truncate();
    VerbEvent::insert(['id' => 1, 'type' => 'E1', 'data' => '{}', 'metadata' => '{}']);
    VerbEvent::insert(['id' => 2, 'type' => 'E1', 'data' => '{}', 'metadata' => '{}']);
    VerbEvent::insert(['id' => 3, 'type' => 'E1', 'data' => '{}', 'metadata' => '{}']);
    VerbEvent::insert(['id' => 4, 'type' => 'E1', 'data' => '{}', 'metadata' => '{}']);
    VerbEvent::insert(['id' => 5, 'type' => 'E1', 'data' => '{}', 'metadata' => '{}']);
    VerbEvent::insert(['id' => 6, 'type' => 'E1', 'data' => '{}', 'metadata' => '{}']);

    // Attach events to different states
    VerbStateEvent::truncate();
    VerbStateEvent::insert(['id' => 1, 'event_id' => 1, 'state_id' => 1, 'state_type' => 'S1']);
    VerbStateEvent::insert(['id' => 2, 'event_id' => 2, 'state_id' => 1, 'state_type' => 'S1']);
    VerbStateEvent::insert(['id' => 3, 'event_id' => 2, 'state_id' => 2, 'state_type' => 'S1']);
    VerbStateEvent::insert(['id' => 4, 'event_id' => 3, 'state_id' => 2, 'state_type' => 'S1']);
    VerbStateEvent::insert(['id' => 5, 'event_id' => 4, 'state_id' => 1, 'state_type' => 'S1']);
    VerbStateEvent::insert(['id' => 6, 'event_id' => 5, 'state_id' => 1, 'state_type' => 'S1']);
    VerbStateEvent::insert(['id' => 7, 'event_id' => 5, 'state_id' => 2, 'state_type' => 'S1']);
    VerbStateEvent::insert(['id' => 8, 'event_id' => 6, 'state_id' => 2, 'state_type' => 'S1']);

    // CASE: All events have snapshots (same event ID)
    VerbSnapshot::truncate();
    VerbSnapshot::insert(['id' => 1, 'state_id' => 1, 'type' => 'S1', 'data' => '{}', 'last_event_id' => 2]);
    VerbSnapshot::insert(['id' => 2, 'state_id' => 2, 'type' => 'S1', 'data' => '{}', 'last_event_id' => 2]);

    $results = Magic::query('S1', 2)->sortBy('state_id')->values();

    expect($results)->toHaveCount(2);

    expect($results[0])
        ->toHaveProperty('state_id', '1')
        ->toHaveProperty('state_type', 'S1')
        ->toHaveProperty('data', '{}')
        ->toHaveProperty('last_event_id', '2');

    expect($results[1])
        ->toHaveProperty('state_id', '2')
        ->toHaveProperty('state_type', 'S1')
        ->toHaveProperty('data', '{}')
        ->toHaveProperty('last_event_id', '2');

    // CASE: All events have snapshots (different event IDs)
    VerbSnapshot::truncate();
    VerbSnapshot::insert(['id' => 1, 'state_id' => 1, 'type' => 'S1', 'data' => '{}', 'last_event_id' => 2]);
    VerbSnapshot::insert(['id' => 2, 'state_id' => 2, 'type' => 'S1', 'data' => '{}', 'last_event_id' => 5]);

    $results = Magic::query('S1', 2)->sortBy('state_id')->values();

    expect($results)->toHaveCount(2);

    expect($results[0])
        ->toHaveProperty('state_id', '1')
        ->toHaveProperty('state_type', 'S1')
        ->toHaveProperty('data', '{}')
        ->toHaveProperty('last_event_id', '2');

    expect($results[1])
        ->toHaveProperty('state_id', '2')
        ->toHaveProperty('state_type', 'S1')
        ->toHaveProperty('data', '{}')
        ->toHaveProperty('last_event_id', '5');

    // CASE: One event has a snapshot, the other doesn't
    VerbSnapshot::truncate();
    VerbSnapshot::insert(['id' => 2, 'state_id' => 2, 'type' => 'S1', 'data' => '{}', 'last_event_id' => 3]);

    $results = Magic::query('S1', 2)
        ->sortBy('state_id')
        ->values();

    expect($results)->toHaveCount(2);

    expect($results[0])
        ->toHaveProperty('state_id', '1')
        ->toHaveProperty('state_type', 'S1')
        ->toHaveProperty('data', null)
        ->toHaveProperty('last_event_id', null);

    expect($results[1])
        ->toHaveProperty('state_id', '2')
        ->toHaveProperty('state_type', 'S1')
        ->toHaveProperty('data', '{}')
        ->toHaveProperty('last_event_id', '3');
});
