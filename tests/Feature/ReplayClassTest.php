<?php

use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Contracts\StoresEvents;
use Thunk\Verbs\Contracts\StoresSnapshots;
use Thunk\Verbs\Event;
use Thunk\Verbs\Lifecycle\Dispatcher;
use Thunk\Verbs\Lifecycle\Phases;
use Thunk\Verbs\Lifecycle\StateManager;
use Thunk\Verbs\State;
use Thunk\Verbs\State\Cache\InMemoryCache;
use Thunk\Verbs\Support\Replay;
use Thunk\Verbs\Support\StateInstanceCache;

it('can rebuild state from events', function () {
    $events = collect(array_fill(0, 10, ReplayClassTestEvent::make(state: 1)->event));
    $timeline = new Replay(
        states: new StateManager(
            dispatcher: app(Dispatcher::class),
            snapshots: app(StoresSnapshots::class),
            events: app(StoresEvents::class),
            states: new StateInstanceCache,
        ),
        events: $events,
        phases: Phases::all()
    );

    $timeline->handle();

    expect($timeline->states->states->cache)
        ->toHaveCount(1);

    expect($timeline->states->load(1, ReplayClassTestState::class)->count)
        ->toBe(10);
});

it('can cache and retrieve state across events', function () {
    $events = collect(array_fill(0, 10, ReplayClassTestEvent::make(state: 1)->event));

    $timeline = new Replay(
        states: new StateManager(
            dispatcher: app(Dispatcher::class),
            events: app(StoresEvents::class),
            caches: [
                new InMemoryCache,
                // new RedisCache,
                // new DatabaseCache
            ]
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
