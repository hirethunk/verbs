<?php

use Thunk\Verbs\Event;
use Thunk\Verbs\State;
use Laravel\Prompts\Key;
use Laravel\Prompts\Prompt;
use Thunk\Verbs\Facades\Id;
use Thunk\Verbs\Facades\Verbs;
use Thunk\Verbs\Commands\ReplayCommand;
use Thunk\Verbs\Lifecycle\StateManager;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;

beforeEach(function () {
    $GLOBALS['replay_test_counts'] = [];
    $GLOBALS['handle_count'] = 0;
});

it('can replay events', function () {
    $state1_id = Id::make();
    $state2_id = Id::make();

    // State 1
    ReplayCommandTestEvent::fire(add: 2, subtract: 0, state_id: $state1_id); // 2
    ReplayCommandTestEvent::fire(add: 2, subtract: 0, state_id: $state1_id); // 4
    ReplayCommandTestEvent::fire(add: 0, subtract: 1, state_id: $state1_id); // 3
    ReplayCommandTestEvent::fire(add: 1, subtract: 1, state_id: $state1_id); // 3
    ReplayCommandTestEvent::fire(add: 1, subtract: 2, state_id: $state1_id); // 2

    // State 2
    ReplayCommandTestEvent::fire(add: 5, subtract: 2, state_id: $state2_id); // 3
    ReplayCommandTestEvent::fire(add: 2, subtract: 2, state_id: $state2_id); // 3
    ReplayCommandTestEvent::fire(add: 0, subtract: 3, state_id: $state2_id); // 0
    ReplayCommandTestEvent::fire(add: 9, subtract: 4, state_id: $state2_id); // 5
    ReplayCommandTestEvent::fire(add: 1, subtract: 2, state_id: $state2_id); // 4

    Verbs::commit();

    expect(app(StateManager::class)->load($state1_id, ReplayCommandTestState::class)->count)
        ->toBe(2)
        ->and($GLOBALS['replay_test_counts'][$state1_id])
        ->toBe(2)
        ->and(app(StateManager::class)->load($state2_id, ReplayCommandTestState::class)->count)
        ->toBe(4)
        ->and($GLOBALS['replay_test_counts'][$state2_id])
        ->toBe(4)
        ->and($GLOBALS['handle_count'])->toBe(10);

    // Reset 'projected' state and change data that only is touched when not replaying
    $GLOBALS['replay_test_counts'] = [];
    $GLOBALS['handle_count'] = 1337;

    config(['app.env' => 'testing']);
    $this->artisan(ReplayCommand::class);

    expect(app(StateManager::class)->load($state1_id, ReplayCommandTestState::class)->count)
        ->toBe(2)
        ->and($GLOBALS['replay_test_counts'][$state1_id])
        ->toBe(2)
        ->and(app(StateManager::class)->load($state2_id, ReplayCommandTestState::class)->count)
        ->toBe(4)
        ->and($GLOBALS['replay_test_counts'][$state2_id])
        ->toBe(4)
        ->and($GLOBALS['handle_count'])->toBe(1337);
});

class ReplayCommandTestEvent extends Event
{
    public function __construct(
        public int $add = 0,
        public int $subtract = 0,
        #[StateId(ReplayCommandTestState::class)] public ?int $state_id = null,
    ) {
    }

    public function apply(ReplayCommandTestState $state)
    {
        $state->count += $this->add;
        $state->count -= $this->subtract;
    }

    public function handle()
    {
        $GLOBALS['replay_test_counts'][$this->state_id] ??= 0;
        $GLOBALS['replay_test_counts'][$this->state_id] += $this->add;
        $GLOBALS['replay_test_counts'][$this->state_id] -= $this->subtract;

        Verbs::unlessReplaying(fn () => $GLOBALS['handle_count']++);
    }
}

class ReplayCommandTestState extends State
{
    public int $count = 0;
}
