<?php

use Illuminate\Support\Facades\DB;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Contracts\StoresEvents;
use Thunk\Verbs\Event;
use Thunk\Verbs\Facades\Verbs;
use Thunk\Verbs\State;

/*
 * Replay must stream: reading the event store used to iterate the entire
 * stream up front (and remember() every model), which held every event in
 * memory at once and OOM'd large replays (#224). These tests pin that reads
 * are lazy and that memory stays flat no matter how many events exist.
 */

function insertSyntheticEvents(int $count, int $state_id): void
{
    $now = now()->format('Y-m-d H:i:s');
    $events = [];
    $pivots = [];

    for ($i = 0; $i < $count; $i++) {
        $event_id = snowflake_id();

        $events[] = [
            'id' => $event_id,
            'type' => StreamingReplayTestEvent::class,
            'data' => json_encode(['state_id' => $state_id]),
            'metadata' => '{}',
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $pivots[] = [
            'id' => snowflake_id(),
            'event_id' => $event_id,
            'state_id' => $state_id,
            'state_type' => StreamingReplayTestState::class,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    foreach (array_chunk($events, 1000) as $chunk) {
        DB::table('verb_events')->insert($chunk);
    }

    foreach (array_chunk($pivots, 1000) as $chunk) {
        DB::table('verb_state_events')->insert($chunk);
    }
}

test('read() executes no queries until the stream is iterated', function () {
    StreamingReplayTestEvent::fire(state_id: snowflake_id());
    Verbs::commit();

    $queries = 0;
    DB::listen(function () use (&$queries) {
        $queries++;
    });

    $events = app(StoresEvents::class)->read();

    expect($queries)->toBe(0);

    expect($events->first())->toBeInstanceOf(StreamingReplayTestEvent::class)
        ->and($queries)->toBeGreaterThan(0);
});

test('streaming a large event store keeps memory flat', function () {
    insertSyntheticEvents(20_000, snowflake_id());

    $baseline = memory_get_usage();
    $high_water = 0;
    $count = 0;

    foreach (app(StoresEvents::class)->read() as $event) {
        if (++$count % 1000 === 0) {
            $high_water = max($high_water, memory_get_usage() - $baseline);
        }
    }

    expect($count)->toBe(20_000)
        ->and($high_water)->toBeLessThan(8 * 1024 * 1024);
});

test('a large replay stays within a flat memory envelope and produces correct state', function () {
    $state_id = snowflake_id();

    insertSyntheticEvents(10_000, $state_id);

    $baseline = memory_get_usage();
    $high_water = 0;

    Verbs::replay(afterEach: function () use (&$high_water, $baseline) {
        $high_water = max($high_water, memory_get_usage() - $baseline);
    });

    expect($high_water)->toBeLessThan(16 * 1024 * 1024)
        ->and(StreamingReplayTestState::load($state_id)->count)->toBe(10_000);
});

class StreamingReplayTestState extends State
{
    public int $count = 0;
}

class StreamingReplayTestEvent extends Event
{
    #[StateId(StreamingReplayTestState::class)]
    public int $state_id;

    public function apply(StreamingReplayTestState $state): void
    {
        $state->count++;
    }
}
