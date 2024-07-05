<?php

use Thunk\Verbs\Attributes\Hooks\Listen;
use Thunk\Verbs\Facades\Verbs;

beforeEach(fn () => $GLOBALS['outside_listener_log'] = []);

it('can trigger listeners outside the event object', function () {
    Verbs::fake();
    Verbs::listen(OutsideListenersTestListener::class);

    OutsideListenersTestEvent1::fire(message: 'test 1');
    OutsideListenersTestEvent2::fire(message: 'test 2');
    OutsideListenersTestEvent1::fire(message: 'test 1b');

    Verbs::commit();

    expect($GLOBALS['outside_listener_log'])->toBe([
        'reflection: test 1',
        'attr: test 1 (OutsideListenersTestEvent1)',
        'attr: test 2 (OutsideListenersTestEvent2)',
        'reflection: test 1b',
        'attr: test 1b (OutsideListenersTestEvent1)',
    ]);
});

class OutsideListenersTestEvent1 extends \Thunk\Verbs\Event
{
    public function __construct(public string $message)
    {
    }
}

class OutsideListenersTestEvent2 extends \Thunk\Verbs\Event
{
    public function __construct(public string $message)
    {
    }
}

class OutsideListenersTestListener
{
    public function viaReflection(OutsideListenersTestEvent1 $event)
    {
        $GLOBALS['outside_listener_log'][] = "reflection: {$event->message}";
    }

    #[Listen(OutsideListenersTestEvent1::class)]
    #[Listen(OutsideListenersTestEvent2::class)]
    public function viaAttribute($e)
    {
        $type = class_basename($e);
        $GLOBALS['outside_listener_log'][] = "attr: {$e->message} ({$type})";
    }
}
