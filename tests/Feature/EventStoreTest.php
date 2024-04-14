<?php

use Thunk\Verbs\Attributes\Autodiscovery\AppliesToSingletonState;
use Thunk\Verbs\Attributes\Autodiscovery\AppliesToState;
use Thunk\Verbs\Contracts\StoresEvents;
use Thunk\Verbs\Event;
use Thunk\Verbs\Lifecycle\EventStore;
use Thunk\Verbs\Lifecycle\MetadataManager;
use Thunk\Verbs\Lifecycle\StateManager;
use Thunk\Verbs\State;


it('reads and writes stateless events normally', function () {
    $store = new EventStore(app(MetadataManager::class));

    $store->write([
        new EventStoreTestEvent(1),
        new EventStoreTestEvent(2),
        new EventStoreTestEvent(3),
        new EventStoreTestEvent(4),
        new EventStoreTestEvent(5),
    ]);

    expect($store->read()->map(fn (Event $event) => $event->id)->all())
        ->toBe([1, 2, 3, 4, 5])
        ->and($store->read(after_id: 2)->map(fn (Event $event) => $event->id)->all())
        ->toBe([3, 4, 5]);
});

it('reads and writes stateful events normally', function () {
    app()->instance(StoresEvents::class, $store = new EventStore(app(MetadataManager::class)));

    $state1 = app(StateManager::class)->load(
        1001,
        type: EventStoreTestState::class,
    );

    $state2 = app(StateManager::class)->load(
        1002,
        type: EventStoreTestState::class,
    );

    // State IDs = 100X, Event IDs = X0Y (X = state, Y = event)

    $store->write([
        new EventStoreTestStatefulEvent(state_id: 1001, id: 101),
        new EventStoreTestStatefulEvent(state_id: 1002, id: 201),
        new EventStoreTestStatefulEvent(state_id: 1001, id: 102),
        new EventStoreTestStatefulEvent(state_id: 1002, id: 202),
        new EventStoreTestStatefulEvent(state_id: 1002, id: 203),
        new EventStoreTestStatefulEvent(state_id: 1001, id: 103),
    ]);

    expect($store->read(state: $state1)->map(fn (Event $event) => $event->id)->all())
        ->toBe([101, 102, 103])
        ->and($store->read(state: $state2)->map(fn (Event $event) => $event->id)->all())
        ->toBe([201, 202, 203])
        ->and($store->read(state: $state2, after_id: 201)->map(fn (Event $event) => $event->id)->all())
        ->toBe([202, 203]);
});

it('reads and writes singleton state events normally', function (){
    app()->instance(StoresEvents::class, $store = new EventStore(app(MetadataManager::class)));

    $state = app(StateManager::class)->load(
        1001,
        type: EventStoreTestSingletonState::class,
    );

    $store->write([
        new EventStoreTestSingletonEvent(state_id: 1001, id: 101),
        new EventStoreTestSingletonEvent(state_id: 1001, id: 102),
        new EventStoreTestSingletonEvent(state_id: 1001, id: 103),
        new EventStoreTestSingletonEvent(state_id: 1002, id: 104),
    ]);

    expect($store->read(state: $state, singleton: true)->map(fn (Event $event) => $event->id)->all())
        ->toBe([101, 102, 103, 104])
        ->and($store->read(state: $state, after_id: 101, singleton: true)->map(fn (Event $event) => $event->id)->all())
        ->toBe([102, 103, 104]);
});

class EventStoreTestEvent extends Event
{
    public function __construct(?int $id = null)
    {
        $this->id = $id ?? snowflake_id();
    }
}

class UncommittedEventStoreTestEvent extends Event
{
    public function __construct(?int $id = null)
    {
        $this->id = $id ?? snowflake_id();
    }
}

class EventStoreTestState extends State
{
}

#[AppliesToState(EventStoreTestState::class, 'state_id')]
class EventStoreTestStatefulEvent extends Event
{
    public function __construct(
        public ?int $state_id = null,
        ?int $id = null
    ) {
        $this->id = $id ?? snowflake_id();
    }
}

class EventStoreTestSingletonState extends State
{
}

#[AppliesToSingletonState(EventStoreTestSingletonState::class)]
class EventStoreTestSingletonEvent extends Event
{
    public function __construct(
        public ?int $state_id = null,
        ?int $id = null
    ) {
        $this->id = $id ?? snowflake_id();
    }
}
