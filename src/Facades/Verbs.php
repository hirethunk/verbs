<?php

namespace Thunk\Verbs\Facades;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Facade;
use Thunk\Verbs\Event;
use Thunk\Verbs\Lifecycle\Broker;

/**
 * @method static bool commit()
 * @method static bool isReplaying()
 * @method static void unlessReplaying(callable $callback)
 * @method static int|string|null toId($id)
 * @method static Event fire(Event $event)
 * @method static void createMetadataUsing(callable $callback)
 * @method static void commitImmediately(bool $commit_immediately = true)
 * @method static CarbonInterface realNow()
 */
class Verbs extends Facade
{
    protected static function getFacadeAccessor()
    {
        return Broker::class;
    }
}
