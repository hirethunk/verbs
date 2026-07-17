<?php

use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Attributes\Hooks\Once;
use Thunk\Verbs\Event;
use Thunk\Verbs\Facades\Verbs;
use Thunk\Verbs\State;

beforeEach(function () {
    OnceHookCounters::$once = 0;
    OnceHookCounters::$every_time = 0;
});

it('skips #[Once] handle hooks during replay but re-runs plain handle hooks', function () {
    OnceHookTestEvent::fire(state_id: snowflake_id());
    OnceHookPlainTestEvent::fire(state_id: snowflake_id());
    Verbs::commit();

    expect(OnceHookCounters::$once)->toBe(1)
        ->and(OnceHookCounters::$every_time)->toBe(1);

    Verbs::replay();

    expect(OnceHookCounters::$once)->toBe(1)
        ->and(OnceHookCounters::$every_time)->toBe(2);
});

class OnceHookCounters
{
    public static int $once = 0;

    public static int $every_time = 0;
}

class OnceHookTestState extends State
{
    public int $count = 0;
}

class OnceHookTestEvent extends Event
{
    #[StateId(OnceHookTestState::class)]
    public int $state_id;

    public function apply(OnceHookTestState $state): void
    {
        $state->count++;
    }

    #[Once]
    public function handle(): void
    {
        OnceHookCounters::$once++;
    }
}

class OnceHookPlainTestEvent extends Event
{
    #[StateId(OnceHookTestState::class)]
    public int $state_id;

    public function apply(OnceHookTestState $state): void
    {
        $state->count++;
    }

    public function handle(): void
    {
        OnceHookCounters::$every_time++;
    }
}
