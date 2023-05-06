<?php

namespace Thunk\Verbs\Facades;

use Illuminate\Support\Facades\Facade;
use Thunk\Verbs\Contracts\Bus as BusContract;
use Thunk\Verbs\Testing\BusFake;

/**
 * @method static void listen(object $listener)
 * @method static void dispatch(\Thunk\Verbs\Event $event)
 * @method static void replay(\Thunk\Verbs\Event $event)
 * @method static void assertRegistered(string $listener_type)
 * @method static void assertDispatched(string $event_type)
 * @method static void assertReplayed(string $event_type)
 * @method static void assertNothingDispatched()
 * @method static void assertNothingReplayed()
 * @method static void assertNothingDispatchedOrReplayed()
 */
class Bus extends Facade
{
    public static function fake(): BusFake
    {
        if (! static::isFake()) {
            static::swap(new BusFake());
        }

        return static::getFacadeRoot();
    }

    protected static function getFacadeAccessor()
    {
        return BusContract::class;
    }
}
