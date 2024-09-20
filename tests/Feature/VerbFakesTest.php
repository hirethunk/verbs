<?php

use Thunk\Verbs\Contracts\StoresEvents;
use Thunk\Verbs\Event;
use Thunk\Verbs\Facades\Verbs;

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
