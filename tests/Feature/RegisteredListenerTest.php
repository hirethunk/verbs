<?php

use Thunk\Verbs\Attributes\Hooks\On;
use Thunk\Verbs\Event;
use Thunk\Verbs\Facades\Verbs;
use Thunk\Verbs\Lifecycle\Phase;

it('can match a single event by typehinting the specific class', function () {
    Verbs::registerListener(OneListener::class);

    expect(fn() => EventOne::fire())->toThrow(JustOneException::class);
    expect(fn() => EventTwo::fire())->not->toThrow(JustOneException::class);
});

it('can match all events by typehinting the base class', function () {
    Verbs::registerListener(EveryListener::class);

    expect(fn() => EventOne::fire())->toThrow(EveryoneException::class);
    expect(fn() => EventTwo::fire())->toThrow(EveryoneException::class);
});

class EventOne extends Event {}
class EventTwo extends Event {}

class JustOneException extends Exception {}
class EveryoneException extends Exception {}

class EveryListener
{

    #[On(Phase::Boot)]
    public static function every(Event $event)
    {
        throw new EveryoneException;
    }
}

class OneListener
{
    #[On(Phase::Boot)]
    public static function one(EventOne $event)
    {
        throw new JustOneException();
    }
}

