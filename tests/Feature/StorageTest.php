<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Thunk\Verbs\Tests\Fixtures\Events\EventWasFired;
use function Pest\Laravel\assertDatabaseHas;

uses(RefreshDatabase::class);

it('can store an event', function () {
    EventWasFired::fire('test one');
    EventWasFired::fire('test two');

    assertDatabaseHas('verb_events', [
        'event_type' => EventWasFired::class,
        'event_data' => json_encode(['name' => 'test one']),
    ]);

    assertDatabaseHas('verb_events', [
        'event_type' => EventWasFired::class,
        'event_data' => json_encode(['name' => 'test two']),
    ]);
});
