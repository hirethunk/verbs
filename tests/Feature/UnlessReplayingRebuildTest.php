<?php

use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;
use Thunk\Verbs\Facades\Verbs;
use Thunk\Verbs\Models\VerbSnapshot;
use Thunk\Verbs\State;
use Thunk\Verbs\State\StateManager;

beforeEach(fn () => $GLOBALS['rebuild_guard_side_effects'] = 0);

it('does not re-run unlessReplaying side effects during a stale-load rebuild', function () {
    $id = snowflake_id();

    RebuildGuardEvent::fire(state_id: $id);
    Verbs::commit();

    expect($GLOBALS['rebuild_guard_side_effects'])->toBe(1);

    // Deleting the snapshots and resetting the scope forces the next load to
    // rebuild the state by re-applying its events.
    VerbSnapshot::query()->delete();
    app(StateManager::class)->reset();

    expect(RebuildGuardState::load($id)->count)->toBe(1)
        ->and($GLOBALS['rebuild_guard_side_effects'])->toBe(1);
});

it('does not re-run unlessReplaying side effects during an explicit replay', function () {
    $id = snowflake_id();

    RebuildGuardEvent::fire(state_id: $id);
    Verbs::commit();

    Verbs::replay();

    expect(RebuildGuardState::load($id)->count)->toBe(1)
        ->and($GLOBALS['rebuild_guard_side_effects'])->toBe(1);
});

class RebuildGuardState extends State
{
    public int $count = 0;
}

class RebuildGuardEvent extends Event
{
    #[StateId(RebuildGuardState::class)]
    public int $state_id;

    public function apply(RebuildGuardState $state): void
    {
        $state->count++;

        Verbs::unlessReplaying(fn () => $GLOBALS['rebuild_guard_side_effects']++);
    }
}
