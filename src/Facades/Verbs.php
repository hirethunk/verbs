<?php

namespace Thunk\Verbs\Facades;

use Illuminate\Support\Facades\Facade;
use Thunk\Verbs\Event;
use Thunk\Verbs\Lifecycle\Broker;

/**
 * @method Event fire(Event $event)
 * @method bool commit()
 * @method void unlessReplaying(callable $callback)
 * @method bool isReplaying()
 * @method int|string toId($id)
 */
class Verbs extends Facade
{
    protected static function getFacadeAccessor()
    {
        return Broker::class;
    }
}
