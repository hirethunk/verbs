<?php

namespace Thunk\Verbs\Facades;

use Illuminate\Support\Facades\Facade;
use Thunk\Verbs\Context;
use Thunk\Verbs\Contracts\ManagesContext;
use Thunk\Verbs\Testing\ContextRepositoryFake;

/**
 * @method static Context sync(Context $context)
 * @method static void assertApplied(string $event_type)
 * @method static void assertNothingApplied()
 */
class Contexts extends Facade
{
    public static function fake(): ContextRepositoryFake
    {
        if (! static::isFake()) {
            static::swap(new ContextRepositoryFake());
        }

        return static::getFacadeRoot();
    }

    protected static function getFacadeAccessor()
    {
        return ManagesContext::class;
    }
}
