<?php

use Illuminate\Support\Facades\DB;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;
use Thunk\Verbs\State;

/*
 * Discovery and event reads must never embed an unbounded id list as query
 * parameters: SQLite caps bound parameters (999 on older builds, 32,766 since
 * 3.32), so a connected component crossing that line used to throw mid-request.
 * This canary rebuilds a component of >32k events and must simply succeed.
 */

test('reconstituting a component larger than the SQLite parameter limit works', function () {
    $state_id = snowflake_id();
    $now = now()->format('Y-m-d H:i:s');

    $events = [];
    $pivots = [];

    for ($i = 0; $i < 33_000; $i++) {
        $event_id = snowflake_id();

        $events[] = [
            'id' => $event_id,
            'type' => LargeComponentTestEvent::class,
            'data' => json_encode(['state_id' => $state_id]),
            'metadata' => '{}',
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $pivots[] = [
            'id' => snowflake_id(),
            'event_id' => $event_id,
            'state_id' => $state_id,
            'state_type' => LargeComponentTestState::class,
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

    // No snapshot exists, so this load discovers the full 33k-event component
    // and replays it—every query along the way must stay under parameter caps.
    expect(LargeComponentTestState::load($state_id)->count)->toBe(33_000);
});

class LargeComponentTestState extends State
{
    public int $count = 0;
}

class LargeComponentTestEvent extends Event
{
    #[StateId(LargeComponentTestState::class)]
    public int $state_id;

    public function apply(LargeComponentTestState $state): void
    {
        $state->count++;
    }
}
