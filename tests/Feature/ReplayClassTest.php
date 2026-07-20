<?php

use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;
use Thunk\Verbs\Lifecycle\Phases;
use Thunk\Verbs\State;
use Thunk\Verbs\State\Cache\InMemoryCache;
use Thunk\Verbs\State\RebuildResolver;
use Thunk\Verbs\State\StateManager;
use Thunk\Verbs\Support\Replay;

it('can rebuild state from events', function () {
    $events = collect(array_fill(0, 10, ReplayClassTestEvent::make(state: 1)->event));
    $replay = new Replay(
        states: new StateManager(
            cache: new InMemoryCache,
            resolver: new RebuildResolver,
        ),
        events: $events,
        phases: Phases::all()
    );

    $replay->handle();

    expect($replay->states->cache->values())
        ->toHaveCount(1);

    expect($replay->states->load(ReplayClassTestState::class, '1')->count)
        ->toBe(10);
});

it('can cache and retrieve state across events', function () {
    $states = new StateManager(cache: new InMemoryCache, resolver: new RebuildResolver);
    $events = collect(array_fill(0, 10, ReplayClassTestEvent::make(state: 1)->event));

    (new Replay(states: $states, events: $events, phases: Phases::all()))->handle();

    // The same cached instance is reused for every event (rather than rebuilt
    // each time), so all ten increments land on one state...
    $state = $states->load(ReplayClassTestState::class, '1');

    expect($state->count)->toBe(10)
        // ...and retrieving it again returns that very same instance.
        ->and($states->load(ReplayClassTestState::class, '1'))->toBe($state);
});

class ReplayClassTestEvent extends Event
{
    #[StateId(ReplayClassTestState::class)]
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
