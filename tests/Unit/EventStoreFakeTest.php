<?php

use Thunk\Verbs\Attributes\Autodiscovery\AppliesToState;
use Thunk\Verbs\Contracts\StoresEvents;
use Thunk\Verbs\Event;
use Thunk\Verbs\Lifecycle\MetadataManager;
use Thunk\Verbs\Lifecycle\StateManager;
use Thunk\Verbs\State;
use Thunk\Verbs\Testing\EventStoreFake;

it('performs assertions', function () {
    $store = new EventStoreFake(app(MetadataManager::class));

    // assertNothingCommitted
    $store->assertNothingCommitted();

    $store->write([
        $event1 = new EventStoreFakeTestEvent(),
        $event2 = new EventStoreFakeTestEvent(),
        $event3 = new EventStoreFakeTestEvent(),
        $event4 = new EventStoreFakeTestEvent(),
        $event5 = new EventStoreFakeTestEvent(),
    ]);

    // committed() and hasCommitted()
    expect($store->committed(EventStoreFakeTestEvent::class)->count())->toBe(5)
        ->and($store->committed(UncommittedEventStoreFakeTestEvent::class)->count())->toBe(0)
        ->and($store->hasCommitted(EventStoreFakeTestEvent::class))->toBeTrue()
        ->and($store->hasCommitted(UncommittedEventStoreFakeTestEvent::class))->toBeFalse();

    // assertCommitted() with type-hinted callback
    $store->assertCommitted(fn (EventStoreFakeTestEvent $event) => $event->id === $event1->id);
    $store->assertCommitted(fn (EventStoreFakeTestEvent $event) => $event->id === $event2->id);
    $store->assertCommitted(fn (EventStoreFakeTestEvent $event) => $event->id === $event3->id);
    $store->assertCommitted(fn (EventStoreFakeTestEvent $event) => $event->id === $event4->id);
    $store->assertCommitted(fn (EventStoreFakeTestEvent $event) => $event->id === $event5->id);

    // assertCommitted() with explicitly-typed callback
    $store->assertCommitted(EventStoreFakeTestEvent::class, fn ($event) => $event->id === $event1->id);
    $store->assertCommitted(EventStoreFakeTestEvent::class, fn ($event) => $event->id === $event2->id);
    $store->assertCommitted(EventStoreFakeTestEvent::class, fn ($event) => $event->id === $event3->id);
    $store->assertCommitted(EventStoreFakeTestEvent::class, fn ($event) => $event->id === $event4->id);
    $store->assertCommitted(EventStoreFakeTestEvent::class, fn ($event) => $event->id === $event5->id);

    // assertCommitted() with class name
    $store->assertCommitted(EventStoreFakeTestEvent::class);
    $store->assertCommitted(EventStoreFakeTestEvent::class, 5);

    // assertNotCommitted() with type-hinted callback
    $store->assertNotCommitted(fn (EventStoreFakeTestEvent $event) => $event->id === 0);
    $store->assertNotCommitted(fn (UncommittedEventStoreFakeTestEvent $event) => $event->id === 0);

    // assertNotCommitted() with explicitly-typed callback
    $store->assertNotCommitted(EventStoreFakeTestEvent::class, fn ($event) => $event->id === 0);
    $store->assertNotCommitted(UncommittedEventStoreFakeTestEvent::class, fn ($event) => $event->id === 0);
});

it('reads and writes stateless events normally', function () {
    $store = new EventStoreFake(app(MetadataManager::class));

    $store->write([
        new EventStoreFakeTestEvent(1),
        new EventStoreFakeTestEvent(2),
        new EventStoreFakeTestEvent(3),
        new EventStoreFakeTestEvent(4),
        new EventStoreFakeTestEvent(5),
    ]);

    expect($store->read()->map(fn (Event $event) => $event->id)->all())
        ->toBe([1, 2, 3, 4, 5])
        ->and($store->read(after_id: 2)->map(fn (Event $event) => $event->id)->all())
        ->toBe([3, 4, 5]);
});

it('reads and writes stateful events normally', function () {
    app()->instance(StoresEvents::class, $store = new EventStoreFake(app(MetadataManager::class)));

    $state1 = app(StateManager::class)->load(
        1001,
        type: EventStoreFakeTestState::class,
    );

    $state2 = app(StateManager::class)->load(
        1002,
        type: EventStoreFakeTestState::class,
    );

    // State IDs = 100X, Event IDs = X0Y (X = state, Y = event)

    $store->write([
        new EventStoreFakeTestStatefulEvent(state_id: 1001, id: 101),
        new EventStoreFakeTestStatefulEvent(state_id: 1002, id: 201),
        new EventStoreFakeTestStatefulEvent(state_id: 1001, id: 102),
        new EventStoreFakeTestStatefulEvent(state_id: 1002, id: 202),
        new EventStoreFakeTestStatefulEvent(state_id: 1002, id: 203),
        new EventStoreFakeTestStatefulEvent(state_id: 1001, id: 103),
    ]);

    expect($store->read(state: $state1)->map(fn (Event $event) => $event->id)->all())
        ->toBe([101, 102, 103])
        ->and($store->read(state: $state2)->map(fn (Event $event) => $event->id)->all())
        ->toBe([201, 202, 203])
        ->and($store->read(state: $state2, after_id: 201)->map(fn (Event $event) => $event->id)->all())
        ->toBe([202, 203]);
});

class EventStoreFakeTestEvent extends Event
{
    public function __construct(?int $id = null)
    {
        $this->id = $id ?? snowflake_id();
    }
}

class UncommittedEventStoreFakeTestEvent extends Event
{
    public function __construct(?int $id = null)
    {
        $this->id = $id ?? snowflake_id();
    }
}

class EventStoreFakeTestState extends State {}

#[AppliesToState(EventStoreFakeTestState::class, 'state_id')]
class EventStoreFakeTestStatefulEvent extends Event
{
    public function __construct(
        public ?int $state_id = null,
        ?int $id = null
    ) {
        $this->id = $id ?? snowflake_id();
    }
}
