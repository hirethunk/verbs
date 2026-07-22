<?php

use Thunk\Verbs\Attributes\Autodiscovery\AppliesToState;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;
use Thunk\Verbs\Facades\Verbs;
use Thunk\Verbs\Models\VerbSnapshot;
use Thunk\Verbs\Replay;
use Thunk\Verbs\SingletonState;
use Thunk\Verbs\State;

beforeEach(fn () => Verbs::commitImmediately());

it('rebuilds a state as of an inclusive upTo ceiling', function () {
    $id = snowflake_id();

    ReplayFreshEvent::fire(state_id: $id);
    $second = ReplayFreshEvent::fire(state_id: $id);
    ReplayFreshEvent::fire(state_id: $id);

    $state = Replay::fresh(ReplayFreshState::class, $id)->upTo($second->id)->run();

    expect($state->count)->toBe(2);
});

it('rebuilds up to head when no ceiling is given', function () {
    $id = snowflake_id();

    ReplayFreshEvent::fire(state_id: $id);
    ReplayFreshEvent::fire(state_id: $id);
    ReplayFreshEvent::fire(state_id: $id);

    expect(Replay::fresh(ReplayFreshState::class, $id)->run()->count)->toBe(3);
});

it('leaves the live instance, live scope, and snapshot untouched', function () {
    $id = snowflake_id();

    $first = ReplayFreshEvent::fire(state_id: $id);
    ReplayFreshEvent::fire(state_id: $id);
    ReplayFreshEvent::fire(state_id: $id);

    $live = ReplayFreshState::load($id);
    $snapshot_before = VerbSnapshot::query()->where('state_id', $id)->value('last_event_id');

    $rebuilt = Replay::fresh(ReplayFreshState::class, $id)->upTo($first->id)->run();

    expect($rebuilt->count)->toBe(1)
        ->and($rebuilt)->not->toBe($live)
        ->and($live->count)->toBe(3)
        ->and(ReplayFreshState::load($id)->count)->toBe(3)
        ->and(VerbSnapshot::query()->where('state_id', $id)->value('last_event_id'))->toEqual($snapshot_before);
});

it('returns a blank shell with a null last_event_id when there are no events', function () {
    $state = Replay::fresh(ReplayFreshState::class, snowflake_id())->run();

    expect($state->last_event_id)->toBeNull()
        ->and($state->count)->toBe(0);
});

it('supports singletons', function () {
    ReplayFreshSingletonEvent::fire();
    ReplayFreshSingletonEvent::fire();

    expect(Replay::fresh(ReplayFreshSingletonState::class)->run()->total)->toBe(2);
});

class ReplayFreshState extends State
{
    public int $count = 0;
}

class ReplayFreshEvent extends Event
{
    #[StateId(ReplayFreshState::class)]
    public int $state_id;

    public function apply(ReplayFreshState $state): void
    {
        $state->count++;
    }
}

class ReplayFreshSingletonState extends SingletonState
{
    public int $total = 0;
}

#[AppliesToState(ReplayFreshSingletonState::class)]
class ReplayFreshSingletonEvent extends Event
{
    public function apply(ReplayFreshSingletonState $state): void
    {
        $state->total++;
    }
}
