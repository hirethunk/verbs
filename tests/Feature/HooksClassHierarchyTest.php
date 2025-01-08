<?php

use Thunk\Verbs\Attributes\Hooks\On;
use Thunk\Verbs\Event;
use Thunk\Verbs\Lifecycle\Dispatcher;
use Thunk\Verbs\Lifecycle\Phase;

it('can match events by type-hinting a specific class', function () {
    app(Dispatcher::class)->register(new HooksClassHierarchyTestOneListener);

    expect(fn () => HooksClassHierarchyTestEvent1::fire())->toThrow(RuntimeException::class, 'one')
        ->and(fn () => HooksClassHierarchyTestEvent2::fire())->not->toThrow(RuntimeException::class);
});

it('can match events by type-hinting an interface', function () {
    app(Dispatcher::class)->register(new HooksClassHierarchyTestInterfaceListener);

    expect(fn () => HooksClassHierarchyTestEvent1::fire())->not->toThrow(RuntimeException::class)
        ->and(fn () => HooksClassHierarchyTestEvent2::fire())->toThrow(RuntimeException::class, 'interface');
});

it('can match all events by type-hinting the base class', function () {
    app(Dispatcher::class)->register(new HooksClassHierarchyTestEveryListener);

    expect(fn () => HooksClassHierarchyTestEvent1::fire())->toThrow(RuntimeException::class, 'every')
        ->and(fn () => HooksClassHierarchyTestEvent2::fire())->toThrow(RuntimeException::class, 'every');
});

interface HooksClassHierarchyTestInterface {}

class HooksClassHierarchyTestEvent1 extends Event {}

class HooksClassHierarchyTestEvent2 extends Event implements HooksClassHierarchyTestInterface {}

class HooksClassHierarchyTestOneListener
{
    #[On(Phase::Validate)]
    public static function one(HooksClassHierarchyTestEvent1 $event)
    {
        throw new RuntimeException('one');
    }
}

class HooksClassHierarchyTestInterfaceListener
{
    #[On(Phase::Validate)]
    public static function interface(HooksClassHierarchyTestInterface $event)
    {
        throw new RuntimeException('interface');
    }
}

class HooksClassHierarchyTestEveryListener
{
    #[On(Phase::Validate)]
    public static function every(Event $event)
    {
        throw new RuntimeException('every');
    }
}
