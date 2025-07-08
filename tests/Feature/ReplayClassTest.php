<?php

use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;
use Thunk\Verbs\Lifecycle\Phases;
use Thunk\Verbs\Lifecycle\StateManager;
use Thunk\Verbs\State;
use Thunk\Verbs\State\Cache\InMemoryCache;
use Thunk\Verbs\Support\Replay;

it('can rebuild state from events', function () {
    $events = collect(array_fill(0, 10, ReplayClassTestEvent::make(state: 1)->event));
    $replay = new Replay(
        states: new StateManager(
            cache: new InMemoryCache
        ),
        events: $events,
        phases: Phases::all()
    );

    $replay->handle();

    expect($replay->states->cache->values())
        ->toHaveCount(1);

    expect($replay->states->load('1', ReplayClassTestState::class)->count)
        ->toBe(10);
});

it('can cache and retrieve state across events', function () {
    $events = collect(array_fill(0, 10, ReplayClassTestEvent::make(state: 1)->event));

    $replay = new Replay(
        states: new StateManager(
            cache: new InMemoryCache
        ),
        events: $events,
        phases: Phases::all()
    );
});

class ReplayClassTestEvent extends Event
{
    #[StateId(ReplayClassTestState::class)] // FIXME: Breaks with State type hint
    public int $state;

    public function apply(ReplayClassTestState $state)
    {
        $state->count++;
    }
}

class ReplayClassTestState extends State
{
    public int $count = 0;
}
