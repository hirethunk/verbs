<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Commands\ReplayCommand;
use Thunk\Verbs\Event;
use Thunk\Verbs\Facades\Id;
use Thunk\Verbs\Facades\Verbs;
use Thunk\Verbs\Lifecycle\StateManager;
use Thunk\Verbs\Models\VerbSnapshot;
use Thunk\Verbs\Models\VerbStateEvent;
use Thunk\Verbs\State;

beforeEach(function () {
    $GLOBALS['replay_test_counts'] = [];
    $GLOBALS['handle_count'] = 0;
    $GLOBALS['times'] = [];
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

it('uses the original event times when replaying', function () {
    \Illuminate\Support\Facades\Date::setTestNow('2024-04-01 12:00:00');
    $state_id = Id::make();
    ReplayCommandTestWormholeEvent::fire(state_id: $state_id);

    Verbs::commit();

    expect(app(StateManager::class)->load($state_id, ReplayCommandTestWormholeState::class)->time->unix())
        ->toBe(CarbonImmutable::parse('2024-04-01 12:00:00')->unix())
        ->and($GLOBALS['time'][$state_id]->unix())
        ->toBe(CarbonImmutable::parse('2024-04-01 12:00:00')->unix());

    $GLOBALS['time'] = [];
    \Illuminate\Support\Facades\Date::setTestNow('2024-05-15 18:00:00');

    config(['app.env' => 'testing']);
    $this->artisan(ReplayCommand::class);

    expect(app(StateManager::class)->load($state_id, ReplayCommandTestWormholeState::class)->time->unix())
        ->toBe(CarbonImmutable::parse('2024-04-01 12:00:00')->unix())
        ->and($GLOBALS['time'][$state_id]->unix())
        ->toBe(CarbonImmutable::parse('2024-04-01 12:00:00')->unix());
});

it('creates new snapshots when replaying', function () {
    Carbon::setTestNow('2024-04-01 12:00:00');

    $state1_id = Id::make();
    $state2_id = Id::make();

    // State 1
    ReplayCommandTestEvent::fire(add: 2, subtract: 0, state_id: $state1_id); // 2
    ReplayCommandTestEvent::fire(add: 0, subtract: 1, state_id: $state1_id); // 1

    // State 2
    ReplayCommandTestEvent::fire(add: 5, subtract: 2, state_id: $state2_id); // 3
    ReplayCommandTestEvent::fire(add: 2, subtract: 2, state_id: $state2_id); // 3

    Verbs::commit();

    expect(VerbSnapshot::count())->toBe(2);

    $snapshot1 = VerbSnapshot::firstWhere('state_id', $state1_id);
    $snapshot2 = VerbSnapshot::firstWhere('state_id', $state2_id);

    expect(json_decode($snapshot1->data)->count)->toBe(1);
    expect($snapshot1->created_at)->toEqual(CarbonImmutable::parse('2024-04-01 12:00:00'));

    expect(json_decode($snapshot2->data)->count)->toBe(3);
    expect($snapshot2->created_at)->toEqual(CarbonImmutable::parse('2024-04-01 12:00:00'));

    Carbon::setTestNow('2024-05-15 18:00:00');

    $GLOBALS['replay_test_counts'] = [];
    $GLOBALS['handle_count'] = 1337;

    config(['app.env' => 'testing']);
    $this->artisan(ReplayCommand::class);

    expect(VerbSnapshot::count())->toBe(2);

    $snapshot1 = VerbSnapshot::firstWhere('state_id', $state1_id);
    $snapshot2 = VerbSnapshot::firstWhere('state_id', $state2_id);

    expect(json_decode($snapshot1->data)->count)->toBe(1);
    expect($snapshot1->created_at)->toEqual(CarbonImmutable::parse('2024-05-15 18:00:00'));

    expect(json_decode($snapshot2->data)->count)->toBe(3);
    expect($snapshot2->created_at)->toEqual(CarbonImmutable::parse('2024-05-15 18:00:00'));
});

it('can reattach events to states when replaying', function () {
    Carbon::setTestNow('2024-04-01 12:00:00');

    $state1_id = Id::make();
    $state2_id = Id::make();

    $state1_event1 = ReplayCommandTestEvent::fire(state_id: $state1_id);
    $state2_event1 = ReplayCommandTestEvent::fire(state_id: $state2_id);
    $state1_event2 = ReplayCommandTestEvent::fire(state_id: $state1_id);
    $state2_event2 = ReplayCommandTestEvent::fire(state_id: $state2_id);

    Verbs::commit();

    expect(VerbStateEvent::count())->toBe(4);

    $this->artisan(ReplayCommand::class);

    expect(VerbStateEvent::count())->toBe(4);

    VerbStateEvent::truncate();

    expect(VerbStateEvent::count())->toBe(0);

    $this->artisan(ReplayCommand::class);

    expect(VerbStateEvent::count())->toBe(0);

    $this->artisan(ReplayCommand::class, ['--reattach' => true]);

    expect(VerbStateEvent::where(['state_id' => $state1_id, 'event_id' => $state1_event1->id])->count())->toBe(1)
        ->and(VerbStateEvent::where(['state_id' => $state1_id, 'event_id' => $state1_event2->id])->count())->toBe(1)
        ->and(VerbStateEvent::where(['state_id' => $state2_id, 'event_id' => $state2_event1->id])->count())->toBe(1)
        ->and(VerbStateEvent::where(['state_id' => $state2_id, 'event_id' => $state2_event2->id])->count())->toBe(1);
});

class ReplayCommandTestEvent extends Event
{
    public function __construct(
        public int $add = 0,
        public int $subtract = 0,
        #[StateId(ReplayCommandTestState::class)] public ?int $state_id = null,
    ) {}

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

class ReplayCommandTestWormholeEvent extends Event
{
    public function __construct(
        #[StateId(ReplayCommandTestWormholeState::class)] public ?int $state_id = null
    ) {}

    public function apply(ReplayCommandTestWormholeState $state): void
    {
        $state->time = CarbonImmutable::now();
    }

    public function handle(): void
    {
        $GLOBALS['time'][$this->state_id] = $this->state(ReplayCommandTestWormholeState::class)->time;
    }
}

class ReplayCommandTestWormholeState extends State
{
    public CarbonImmutable $time;
}
