<?php

use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Attributes\Hooks\Latest;
use Thunk\Verbs\Commands\ReplayCommand;
use Thunk\Verbs\Event;
use Thunk\Verbs\Facades\Id;
use Thunk\Verbs\Facades\Verbs;
use Thunk\Verbs\State;

beforeEach(function () {
    $GLOBALS['replay_test_counts'] = [];
    $GLOBALS['handle_count'] = 0;
});

it('prevents duplicate writes by uniqueBy', function () {
    $state1_id = Id::make();
    $state2_id = Id::make();

    // State 1
    LatestHandleTestEvent::fire(add: 2, state_id: $state1_id); // 2
    LatestHandleTestEvent::fire(add: 2, state_id: $state1_id); // 4

    // State 2
    LatestHandleTestEvent::fire(add: 5, state_id: $state2_id); // 5
    LatestHandleTestEvent::fire(add: 2, state_id: $state2_id); // 7

    Verbs::commit();

    expect($GLOBALS['handle_count'])->toBe(4);

    $GLOBALS['handle_count'] = 0;

    config(['app.env' => 'testing']);
    $this->artisan(ReplayCommand::class);

    expect($GLOBALS['handle_count'])->toBe(2);
});

it('prevents duplicate writes by uniqueBy and type', function () {
    $state1_id = Id::make();
    $state2_id = Id::make();

    // State 1
    LatestHandleTestEvent::fire(add: 2, state_id: $state1_id); // 2
    AnotherLatestHandleTestEvent::fire(add: 2, state_id: $state1_id); // 4
    AnotherLatestHandleTestEvent::fire(add: 2, state_id: $state1_id); // 6

    // State 2
    LatestHandleTestEvent::fire(add: 5, state_id: $state2_id); // 5
    AnotherLatestHandleTestEvent::fire(add: 2, state_id: $state2_id); // 7

    Verbs::commit();

    expect($GLOBALS['handle_count'])->toBe(5);

    $GLOBALS['handle_count'] = 0;

    config(['app.env' => 'testing']);
    $this->artisan(ReplayCommand::class);

    expect($GLOBALS['handle_count'])->toBe(2);
});

class LatestHandleTestEvent extends Event
{
    public function __construct(
        public int $add = 0,
        #[StateId(LatestHandleTestState::class)] public ?int $state_id = null,
    ) {}

    public function apply(LatestHandleTestState $state)
    {
        $state->count += $this->add;
    }

    #[Latest(unique_id: 'state_id')]
    public function handle(): void
    {
        $GLOBALS['handle_count']++;
    }
}

class AnotherLatestHandleTestEvent extends Event
{
    public function __construct(
        public int $add = 0,
        #[StateId(LatestHandleTestState::class)] public ?int $state_id = null,
    ) {}

    public function apply(LatestHandleTestState $state)
    {
        $state->count += $this->add;
    }

    #[Latest(unique_id: 'state_id', type: LatestHandleTestEvent::class)]
    public function handle(): void
    {
        $GLOBALS['handle_count']++;
    }
}

class LatestHandleTestState extends State
{
    public int $count = 0;
}
