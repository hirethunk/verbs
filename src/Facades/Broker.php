<?php

namespace Thunk\Verbs\Facades;

use Illuminate\Support\Facades\Facade;
use Thunk\Verbs\Contracts\BrokersEvents;

/**
 * @method static void originate(\Thunk\Verbs\Event $event)
 * @method static void replay(array|string $event_types = null, int $chunk_size = 1000)
 */
class Broker extends Facade
{
    protected static function getFacadeAccessor()
    {
        return BrokersEvents::class;
    }
}
