<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use function Pest\Laravel\assertDatabaseHas;
use Thunk\Verbs\Attributes\Once;
use Thunk\Verbs\Events\Dispatcher;
use Thunk\Verbs\Events\Event;
use Thunk\Verbs\Events\Playback;

uses(RefreshDatabase::class);

class EventWasFired extends Event
{
    public function __construct(public string $name)
    {
    }
}

beforeEach(fn () => $GLOBALS['heard_events'] = []);

it('can store an event', function () {
    EventWasFired::fire('testing');

    assertDatabaseHas('verb_events', [
        'event_type' => EventWasFired::class,
        'event_data' => json_encode(['name' => 'testing']),
    ]);
});

it('can fire an event and have that event reach a listener', function () {
    app(Dispatcher::class)
        ->registerListener(new class()
        {
            public function thisShouldFire(EventWasFired $event)
            {
                $GLOBALS['heard_events'][] = $event->name;
            }
        });

    EventWasFired::fire('hello!');

    expect($GLOBALS['heard_events'][0])->toBe('hello!');
});

it('listeners marked with Once annotation should not be replayed', function () {
    app(Dispatcher::class)
        ->registerListener(new class()
        {
            public function alwaysFire(EventWasFired $event)
            {
                $GLOBALS['heard_events'][] = "always:{$event->name}";
            }

            #[Once]
            public function fireOnce(EventWasFired $event)
            {
                $GLOBALS['heard_events'][] = "once:{$event->name}";
            }
        });

    EventWasFired::fire('foo');

    expect($GLOBALS['heard_events'])->toBe(['always:foo', 'once:foo']);

    Playback::for(EventWasFired::class)->run();

    expect($GLOBALS['heard_events'])->toBe(['always:foo', 'once:foo', 'always:foo']);
});

it('self-firing event with Once annotation should not be replayed', function () {

    class SelfFiringEventWasFired extends EventWasFired
    {
        public function onFire()
        {
            $GLOBALS['heard_events'][] = "onfire:{$this->name}";
        }
    }

    class OneTimeSelfFiringEventWasFired extends EventWasFired
    {
        #[Once]
        public function onFire()
        {
            $GLOBALS['heard_events'][] = "onfire:once:{$this->name}";
        }
    }

    SelfFiringEventWasFired::fire('foo');
    OneTimeSelfFiringEventWasFired::fire('bar');

    expect($GLOBALS['heard_events'])->toBe(['onfire:foo', 'onfire:once:bar']);

    Playback::all()->run();

    expect($GLOBALS['heard_events'])->toBe(['onfire:foo', 'onfire:once:bar', 'onfire:foo']);
});
