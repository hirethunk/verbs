<?php

use Illuminate\Support\Facades\Date;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;
use Thunk\Verbs\Facades\Verbs;
use Thunk\Verbs\Models\VerbSnapshot;
use Thunk\Verbs\State;

/*
 * The identity map skips re-writing a snapshot for a state that hasn't advanced
 * since it was last persisted (StateManager's dirty-watermark). These pin that
 * behavior through the public path—fire + commit—since the "no redundant write"
 * guarantee is the entire reason the watermark exists.
 */

beforeEach(fn () => Verbs::commitImmediately());

it('writes a snapshot the first time a state advances', function () {
    $id = snowflake_id();

    DirtyTrackingEvent::fire(state_id: $id);

    expect(VerbSnapshot::query()->where('state_id', $id)->count())->toBe(1);
});

it('does not rewrite the snapshot of a state that has not advanced', function () {
    Date::setTestNow('2026-07-20 00:00:00');
    $a = snowflake_id();
    DirtyTrackingEvent::fire(state_id: $a);

    $written_at = VerbSnapshot::query()->where('state_id', $a)->first()->updated_at->format('Y-m-d H:i:s');

    // A later commit that only touches a different state must leave A's row alone.
    Date::setTestNow('2026-07-20 00:05:00');
    DirtyTrackingEvent::fire(state_id: snowflake_id());

    expect(VerbSnapshot::query()->where('state_id', $a)->first()->updated_at->format('Y-m-d H:i:s'))
        ->toBe($written_at)
        ->toBe('2026-07-20 00:00:00');
});

it('never writes a snapshot for a blank state that saw no events', function () {
    $blank = snowflake_id();
    DirtyTrackingState::load($blank); // resident, last_event_id === null

    // A commit driven by an unrelated event must not write the blank state.
    DirtyTrackingEvent::fire(state_id: snowflake_id());

    expect(VerbSnapshot::query()->where('state_id', $blank)->exists())->toBeFalse();
});

class DirtyTrackingState extends State
{
    public int $count = 0;
}

class DirtyTrackingEvent extends Event
{
    #[StateId(DirtyTrackingState::class)]
    public int $state_id;

    public function apply(DirtyTrackingState $state): void
    {
        $state->count++;
    }
}
