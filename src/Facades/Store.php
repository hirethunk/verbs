<?php

namespace Thunk\Verbs\Facades;

use Illuminate\Support\Facades\Facade;
use Thunk\Verbs\Contracts\Store as StoreContract;
use Thunk\Verbs\Testing\StoreFake;

/**
 * @method static void assertSaved(string $event_type)
 * @method static void assertNothingSaved()
 */
class Store extends Facade
{
    public static function fake(): StoreFake
    {
        if (! static::isFake()) {
            static::swap(new StoreFake());
        }

        return static::getFacadeRoot();
    }

    protected static function getFacadeAccessor()
    {
        return StoreContract::class;
    }
}
