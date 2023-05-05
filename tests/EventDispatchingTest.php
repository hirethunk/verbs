<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use function Pest\Laravel\assertDatabaseHas;
use Thunk\Verbs\Attributes\Once;
use Thunk\Verbs\Events\Event;
use Thunk\Verbs\Events\Listener;
use Thunk\Verbs\Events\ListenerRegistry;

uses(RefreshDatabase::class);

class TestEvent extends Event
{
}
class TestListener extends Listener
{
}

it('can dispatch an event', function () {
    expect(TestEvent::fire())->toBeTrue();
});

it('can register a listener', function () {
    $registry = new ListenerRegistry();
    $registry->register(TestListener::class);

    expect($registry->getListeners())->toBe([TestListener::class]);
});

it('can dispatch an event and have that event reach a registered listener', function () {
    global $listener_was_hit;
    $listener_was_hit = false;

    class ShouldBeHit extends Listener
    {
        #[TestEvent]
        public function thisShouldFire(TestEvent $event)
        {
            global $listener_was_hit;
            $listener_was_hit = true;
        }
    }

    $registry = new ListenerRegistry();
    $registry->register(ShouldBeHit::class);

    TestEvent::fire($registry);

    expect($listener_was_hit)->toBeTrue();
});

it('only runs a listener method once if it has the "Once" attribute', function () {
    global $once_listener_was_hit;
    $once_listener_was_hit = 0;

    global $multi_listener_was_hit;
    $multi_listener_was_hit = 0;

    class ShouldBeHitTwice extends Listener
    {
        #[Once(TestEvent::class)]
        public function thisShouldFireOnce(TestEvent $event)
        {
            global $once_listener_was_hit;
            $once_listener_was_hit++;
        }

        #[TestEvent]
        public function thisShouldFireTwice(TestEvent $event)
        {
            global $multi_listener_was_hit;
            $multi_listener_was_hit++;
        }
    }

    $registry = new ListenerRegistry();
    $registry->register(ShouldBeHitTwice::class);

    TestEvent::fire($registry);
    Event::replay($registry);

    expect($once_listener_was_hit)->toBe(1);
    expect($multi_listener_was_hit)->toBe(2);
});

it('can store an event', function () {
    $registry = new ListenerRegistry();
    $registry->register(TestListener::class);

    TestEvent::fire($registry);

    assertDatabaseHas('events', [
        'event_type' => TestEvent::class,
    ]);
});
