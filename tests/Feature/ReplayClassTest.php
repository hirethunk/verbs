<?php

use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;
use Thunk\Verbs\Lifecycle\Lifecycle;
use Thunk\Verbs\Lifecycle\Phases;
use Thunk\Verbs\Replay;
use Thunk\Verbs\State;
use Thunk\Verbs\State\Cache\InMemoryCache;
use Thunk\Verbs\State\RebuildResolver;
use Thunk\Verbs\State\StateManager;

function replayClassTestReplay(StateManager $scope, iterable $events): Replay
{
    return new Replay(
        scope: $scope,
        events: collect($events),
        drive: fn (Event $event) => Lifecycle::run($event, Phases::all()),
    );
}

it('can rebuild state from events', function () {
    $scope = new StateManager(cache: new InMemoryCache, resolver: new RebuildResolver);
    $events = array_fill(0, 10, ReplayClassTestEvent::make(state: 1)->event);

    $replay = replayClassTestReplay($scope, $events);
    $replay->run();

    expect($replay->scope->cache->values())
        ->toHaveCount(1);

    expect($replay->scope->load(ReplayClassTestState::class, '1')->count)
        ->toBe(10);
});

it('can cache and retrieve state across events', function () {
    $scope = new StateManager(cache: new InMemoryCache, resolver: new RebuildResolver);
    $events = array_fill(0, 10, ReplayClassTestEvent::make(state: 1)->event);

    replayClassTestReplay($scope, $events)->run();

    // The same cached instance is reused for every event (rather than rebuilt
    // each time), so all ten increments land on one state...
    $state = $scope->load(ReplayClassTestState::class, '1');

    expect($state->count)->toBe(10)
        // ...and retrieving it again returns that very same instance.
        ->and($scope->load(ReplayClassTestState::class, '1'))->toBe($state);
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
