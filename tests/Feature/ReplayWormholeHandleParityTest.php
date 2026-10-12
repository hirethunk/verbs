<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;
use Thunk\Verbs\Facades\Verbs;
use Thunk\Verbs\State;

beforeEach(function () {
    $GLOBALS['applied_at_initial'] = null;
    $GLOBALS['applied_at_initial_within_handle'] = null;
    $GLOBALS['handled_at_replayed_within_handle'] = null;
    $GLOBALS['applied_at_replayed'] = null;
    $GLOBALS['handled_at_initial'] = null;
    $GLOBALS['handled_at_replayed'] = null;
});

it('keeps handle now identical during replay', function () {
    // We ensure that events are not committed immediately after they are fired.
    Verbs::commitImmediately(false);

    $nowDuringInitialFiring = '2024-01-01T00:00:00Z';
    $nowDuringInitialCommit = '2025-01-01T00:00:00Z';

    // Fire the event with a fixed "now"
    Date::setTestNow($nowDuringInitialFiring);
    ReplayWormholeHandleParityEvent::fire();

    // Commit the event with a different "now".
    Date::setTestNow($nowDuringInitialCommit);
    Verbs::commit();

    // Confirm the different "now" used during apply vs handle.
    expect(true)
        ->and($GLOBALS['applied_at_initial']->toIso8601ZuluString())->toBe($nowDuringInitialFiring)
        ->and($GLOBALS['applied_at_initial_within_handle']->toIso8601ZuluString())->toBe($nowDuringInitialFiring)
        ->and($GLOBALS['handled_at_initial']->toIso8601ZuluString())->toBe($nowDuringInitialCommit);

    // Replay the event.
    Verbs::replay();

    // Compare the initial timestamps vs the replayed timestamps.
    expect(true)
        ->and($GLOBALS['applied_at_initial']->toIso8601ZuluString())->toBe($GLOBALS['applied_at_replayed']->toIso8601ZuluString())
        ->and($GLOBALS['applied_at_initial_within_handle']->toIso8601ZuluString())->toBe($GLOBALS['applied_at_replayed_within_handle']->toIso8601ZuluString())
        ->and($GLOBALS['handled_at_initial']->toIso8601ZuluString())->toBe($GLOBALS['handled_at_replayed']->toIso8601ZuluString());

});

class ReplayWormholeHandleParityEvent extends Event
{
    public function __construct(
        #[StateId(ReplayWormholeHandleParityState::class)] public ?int $state_id = null
    ) {}

    public function apply(ReplayWormholeHandleParityState $state): void
    {
        $state->applied_at = CarbonImmutable::now();
        if (Verbs::isReplaying()) {
            $GLOBALS['applied_at_replayed'] = $state->applied_at;
        } else {
            $GLOBALS['applied_at_initial'] = $state->applied_at;
        }

    }

    public function handle(): void
    {
        if (Verbs::isReplaying()) {
            $GLOBALS['applied_at_replayed_within_handle'] = $this->state(ReplayWormholeHandleParityState::class)->applied_at;
            $GLOBALS['handled_at_replayed'] = CarbonImmutable::now();
        } else {
            $GLOBALS['applied_at_initial_within_handle'] = $this->state(ReplayWormholeHandleParityState::class)->applied_at;
            $GLOBALS['handled_at_initial'] = CarbonImmutable::now();
        }
    }
}

class ReplayWormholeHandleParityState extends State
{
    public CarbonImmutable $applied_at;
}
