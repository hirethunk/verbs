<?php

use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;
use Thunk\Verbs\Exceptions\CannotReplayWithQueuedEvents;
use Thunk\Verbs\Facades\Verbs;
use Thunk\Verbs\Replay;
use Thunk\Verbs\State;

it('rebuilds state from stored events and returns the number replayed', function () {
    Verbs::commitImmediately();

    $id = snowflake_id();
    ReplayFullApiEvent::fire(state_id: $id);
    ReplayFullApiEvent::fire(state_id: $id);
    ReplayFullApiEvent::fire(state_id: $id);

    $count = Replay::full()->run();

    expect($count)->toBe(3)
        ->and(ReplayFullApiState::load($id)->count)->toBe(3);
});

it('passes each replayed event to the callbacks in stored order', function () {
    Verbs::commitImmediately();

    $id = snowflake_id();
    $fired = collect([
        ReplayFullApiEvent::fire(state_id: $id),
        ReplayFullApiEvent::fire(state_id: $id),
        ReplayFullApiEvent::fire(state_id: $id),
    ])->map(fn (Event $event) => $event->id)->all();

    $before = [];
    $after = [];

    Replay::full()
        ->beforeEach(function (Event $event) use (&$before) {
            $before[] = $event->id;
        })
        ->afterEach(function (Event $event) use (&$after) {
            $after[] = $event->id;
        })
        ->run();

    expect($before)->toBe($fired)
        ->and($after)->toBe($fired);
});

it('refuses to replay while events are queued, with no broker involved', function () {
    ReplayFullApiEvent::fire(state_id: snowflake_id());

    expect(fn () => Replay::full()->run())->toThrow(
        CannotReplayWithQueuedEvents::class,
        'queued but uncommitted',
    );
});

it('rebuilds through the full replay under Verbs::fake()', function () {
    Verbs::fake();
    Verbs::commitImmediately();

    $id = snowflake_id();
    ReplayFullApiEvent::fire(state_id: $id);
    ReplayFullApiEvent::fire(state_id: $id);

    $count = Replay::full()->run();

    expect($count)->toBe(2)
        ->and(ReplayFullApiState::load($id)->count)->toBe(2);
});

class ReplayFullApiState extends State
{
    public int $count = 0;
}

class ReplayFullApiEvent extends Event
{
    #[StateId(ReplayFullApiState::class)]
    public int $state_id;

    public function apply(ReplayFullApiState $state): void
    {
        $state->count++;
    }
}
