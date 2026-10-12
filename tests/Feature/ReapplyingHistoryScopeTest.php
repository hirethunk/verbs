<?php

use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;
use Thunk\Verbs\Facades\Verbs;
use Thunk\Verbs\Models\VerbSnapshot;
use Thunk\Verbs\State;
use Thunk\Verbs\State\StateManager;

beforeEach(fn () => $GLOBALS['reapply_scope_throw'] = false);

/*
 * "Are we re-applying history?" is a property of the currently bound scope's
 * resolver, and run() restores the binding in a finally—so the signal must
 * return to false even when a reconstitution dies partway through its event
 * window, and the scope must remain fully usable afterward.
 */
it('reports not re-applying history after a reconstitution throws mid-window', function () {
    $id = snowflake_id();

    ReapplyScopeTestEvent::fire(state_id: $id);
    ReapplyScopeTestEvent::fire(state_id: $id);
    ReapplyScopeTestEvent::fire(state_id: $id);
    Verbs::commit();

    // Deleting the snapshots and resetting the scope forces the next load to
    // rebuild the state by re-applying its events...
    VerbSnapshot::query()->delete();
    app(StateManager::class)->reset();

    // ...and this makes the second of the three applies throw mid-window.
    $GLOBALS['reapply_scope_throw'] = true;

    expect(fn () => ReapplyScopeTestState::load($id))->toThrow(RuntimeException::class);

    expect(app(StateManager::class)->isReapplyingHistory())->toBeFalse()
        ->and(Verbs::isReplaying())->toBeFalse();

    // The scope still works: with apply() healthy again, a fresh load
    // reconstitutes to the correct value.
    $GLOBALS['reapply_scope_throw'] = false;
    app(StateManager::class)->reset();

    expect(ReapplyScopeTestState::load($id)->count)->toBe(3);
});

class ReapplyScopeTestState extends State
{
    public int $count = 0;
}

class ReapplyScopeTestEvent extends Event
{
    #[StateId(ReapplyScopeTestState::class)]
    public int $state_id;

    public function apply(ReapplyScopeTestState $state): void
    {
        $state->count++;

        if ($GLOBALS['reapply_scope_throw'] && $state->count === 2) {
            throw new RuntimeException('Simulated mid-window failure.');
        }
    }
}
