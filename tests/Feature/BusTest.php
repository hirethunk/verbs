<?php

use Thunk\Verbs\Attributes\Once;
use Thunk\Verbs\Facades\Broker;
use Thunk\Verbs\Facades\Store;
use Thunk\Verbs\Tests\Events\EventWasFired;
use Thunk\Verbs\Tests\Events\SelfFiringEventFired;
use Thunk\Verbs\Tests\Events\SelfFiringOnceEventFired;

beforeEach(function () {
    Store::fake();
    $GLOBALS['heard_events'] = [];
});

it('firing an events delivers them listeners', function () {
    registerListener(new class()
    {
        public function always(EventWasFired $event)
        {
            $GLOBALS['heard_events'][] = "always:{$event->name}";
        }

        #[Once]
        public function once(EventWasFired $event)
        {
            $GLOBALS['heard_events'][] = "once:{$event->name}";
        }
    });

    EventWasFired::fire('a');

    expect($GLOBALS['heard_events'])->toBe(['always:a', 'once:a']);

    Broker::replay();

    expect($GLOBALS['heard_events'])->toBe(['always:a', 'once:a', 'always:a']);
});

it('self-firing events are triggered', function () {
    SelfFiringEventFired::fire('a');
    SelfFiringOnceEventFired::fire('b');

    expect($GLOBALS['heard_events'])->toBe(['self-always:a', 'self-once:b']);

    Broker::replay();

    expect($GLOBALS['heard_events'])->toBe(['self-always:a', 'self-once:b', 'self-always:a']);
});

it('can register Closures as listeners', function() {
	registerListener(function(EventWasFired $event) {
		$GLOBALS['heard_events'][] = "closure:{$event->name}";
	});
	
	EventWasFired::fire('a');
	
	expect($GLOBALS['heard_events'])->toBe(['closure:a']);
	
	Broker::replay();
	
	expect($GLOBALS['heard_events'])->toBe(['closure:a', 'closure:a']);
});
