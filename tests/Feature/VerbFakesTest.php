<?php

use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Contracts\StoresEvents;
use Thunk\Verbs\Event;
use Thunk\Verbs\Facades\Verbs;
use Thunk\Verbs\State;

it('performs event store assertions', function () {
    Verbs::fake();

    // assertNothingCommitted
    Verbs::assertNothingCommitted();

    app(StoresEvents::class)->write([
        $event1 = new VerbFakesTestEvent,
        $event2 = new VerbFakesTestEvent,
        $event3 = new VerbFakesTestEvent,
        $event4 = new VerbFakesTestEvent,
        $event5 = new VerbFakesTestEvent,
    ]);

    // assertCommitted() with type-hinted callback
    Verbs::assertCommitted(fn (VerbFakesTestEvent $event) => $event->id === $event1->id);
    Verbs::assertCommitted(fn (VerbFakesTestEvent $event) => $event->id === $event2->id);
    Verbs::assertCommitted(fn (VerbFakesTestEvent $event) => $event->id === $event3->id);
    Verbs::assertCommitted(fn (VerbFakesTestEvent $event) => $event->id === $event4->id);
    Verbs::assertCommitted(fn (VerbFakesTestEvent $event) => $event->id === $event5->id);

    // assertCommitted() with explicitly-typed callback
    Verbs::assertCommitted(VerbFakesTestEvent::class, fn ($event) => $event->id === $event1->id);
    Verbs::assertCommitted(VerbFakesTestEvent::class, fn ($event) => $event->id === $event2->id);
    Verbs::assertCommitted(VerbFakesTestEvent::class, fn ($event) => $event->id === $event3->id);
    Verbs::assertCommitted(VerbFakesTestEvent::class, fn ($event) => $event->id === $event4->id);
    Verbs::assertCommitted(VerbFakesTestEvent::class, fn ($event) => $event->id === $event5->id);

    // assertCommitted() with class name
    Verbs::assertCommitted(VerbFakesTestEvent::class);
    Verbs::assertCommitted(VerbFakesTestEvent::class, 5);

    // assertNotCommitted() with type-hinted callback
    Verbs::assertNotCommitted(fn (VerbFakesTestEvent $event) => $event->id === 0);
    Verbs::assertNotCommitted(fn (UncommittedVerbFakesTestEvent $event) => $event->id === 0);

    // assertNotCommitted() with explicitly-typed callback
    Verbs::assertNotCommitted(VerbFakesTestEvent::class, fn ($event) => $event->id === 0);
    Verbs::assertNotCommitted(UncommittedVerbFakesTestEvent::class, fn ($event) => $event->id === 0);
});

it('serves fired events back through the storage contract', function () {
    Verbs::fake();

    $first = VerbFakesTestEvent::fire();
    $second = VerbFakesTestEvent::fire();
    Verbs::commit();

    // A reconstitution under Verbs::fake() streams events from the fake store,
    // so get() returning nothing would silently rebuild states as blanks.
    expect(app(StoresEvents::class)->get([$second->id, $first->id])->map(fn (Event $event) => $event->id)->all())
        ->toBe([$first->id, $second->id]);
});

it('starts a fresh world, discarding anything queued or loaded before the fake', function () {
    $id = snowflake_id();

    VerbFakesFreshWorldEvent::fire(state_id: $id);

    $held = VerbFakesFreshWorldState::load($id);

    expect($held->count)->toBe(1);

    $fake = Verbs::fake();

    // The pre-fake queued event must not leak into the fake world: committing
    // it here would write it to the fake stores as though it happened post-fake.
    Verbs::commit();
    Verbs::assertNothingCommitted();
    $fake->snapshots->assertNothingWritten();

    $fresh = VerbFakesFreshWorldState::load($id);

    expect($fresh)->not->toBe($held)
        ->and($fresh->count)->toBe(0);
});

it('is a no-op when called while already faked', function () {
    $first = Verbs::fake();

    VerbFakesTestEvent::fire();
    Verbs::commit();

    // A second fake() must not start another fresh world: the same fake broker
    // (and everything committed to it) survives.
    expect(Verbs::fake())->toBe($first);

    Verbs::assertCommitted(VerbFakesTestEvent::class);
});

class VerbFakesTestEvent extends Event
{
    public function __construct(?int $id = null)
    {
        $this->id = $id ?? snowflake_id();
    }
}

class UncommittedVerbFakesTestEvent extends Event
{
    public function __construct(?int $id = null)
    {
        $this->id = $id ?? snowflake_id();
    }
}

class VerbFakesFreshWorldState extends State
{
    public int $count = 0;
}

class VerbFakesFreshWorldEvent extends Event
{
    #[StateId(VerbFakesFreshWorldState::class)]
    public int $state_id;

    public function apply(VerbFakesFreshWorldState $state)
    {
        $state->count++;
    }
}
