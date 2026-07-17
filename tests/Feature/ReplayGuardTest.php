<?php

use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;
use Thunk\Verbs\Exceptions\CannotReplayWithQueuedEvents;
use Thunk\Verbs\Facades\Verbs;
use Thunk\Verbs\State;

it('refuses to replay while events are queued but uncommitted', function () {
    ReplayGuardEvent::fire(state_id: snowflake_id());

    expect(fn () => Verbs::replay())->toThrow(
        CannotReplayWithQueuedEvents::class,
        'queued but uncommitted',
    );
});

it('replays once the queue has been committed', function () {
    $id = snowflake_id();

    ReplayGuardEvent::fire(state_id: $id);
    Verbs::commit();

    Verbs::replay();

    expect(ReplayGuardState::load($id)->count)->toBe(1);
});

class ReplayGuardState extends State
{
    public int $count = 0;
}

class ReplayGuardEvent extends Event
{
    #[StateId(ReplayGuardState::class)]
    public int $state_id;

    public function apply(ReplayGuardState $state): void
    {
        $state->count++;
    }
}
