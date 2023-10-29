<?php

namespace Thunk\Verbs\Facades;

use Illuminate\Support\Facades\Facade;
use Thunk\Verbs\Lifecycle\Broker;

/**
 * @method void unlessReplaying(callable $callback)
 */
class Verbs extends Facade
{
    protected static function getFacadeAccessor()
    {
        return Broker::class;
    }
}
