<?php

namespace Thunk\Verbs\Facades;

use Illuminate\Support\Facades\Facade;
use Thunk\Verbs\Contracts\StoresEvents;
use Thunk\Verbs\Testing\EventRepositoryFake;

/**
 * @method static void assertSaved(string $event_type)
 * @method static void assertNothingSaved()
 */
class Store extends Facade
{
    public static function fake(): EventRepositoryFake
    {
        static::swap(new EventRepositoryFake());

        return static::getFacadeRoot();
    }

    protected static function getFacadeAccessor()
    {
        return StoresEvents::class;
    }
}
