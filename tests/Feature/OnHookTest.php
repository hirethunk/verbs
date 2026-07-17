<?php

use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Attributes\Hooks\On;
use Thunk\Verbs\Event;
use Thunk\Verbs\Facades\Verbs;
use Thunk\Verbs\Lifecycle\Phase;
use Thunk\Verbs\State;

beforeEach(function () {
    OnHookCounters::$handle_only = 0;
    OnHookCounters::$replay_only = 0;
    Verbs::listen(OnHookTestListener::class);
});

it('runs #[On] hooks only in the phase they target', function () {
    OnHookTestEvent::fire(state_id: snowflake_id());
    Verbs::commit();

    expect(OnHookCounters::$handle_only)->toBe(1)
        ->and(OnHookCounters::$replay_only)->toBe(0);

    Verbs::replay();

    expect(OnHookCounters::$handle_only)->toBe(1)
        ->and(OnHookCounters::$replay_only)->toBe(1);
});

class OnHookCounters
{
    public static int $handle_only = 0;

    public static int $replay_only = 0;
}

class OnHookTestState extends State
{
    public int $count = 0;
}

class OnHookTestEvent extends Event
{
    #[StateId(OnHookTestState::class)]
    public int $state_id;

    public function apply(OnHookTestState $state): void
    {
        $state->count++;
    }
}

class OnHookTestListener
{
    #[On(Phase::Handle)]
    public function handleOnly(OnHookTestEvent $event): void
    {
        OnHookCounters::$handle_only++;
    }

    #[On(Phase::Replay)]
    public function replayOnly(OnHookTestEvent $event): void
    {
        OnHookCounters::$replay_only++;
    }
}
