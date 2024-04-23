<?php

namespace Thunk\Verbs\Facades;

use Carbon\CarbonInterface;
use Closure;
use Illuminate\Support\Facades\Facade;
use Thunk\Verbs\Contracts\BrokersEvents;
use Thunk\Verbs\Event;
use Thunk\Verbs\Lifecycle\BrokerStore;
use Thunk\Verbs\Testing\EventStoreFake;

/**
 * @method static bool commit()
 * @method static bool isReplaying()
 * @method static void unlessReplaying(callable $callback)
 * @method static Event fire(Event $event)
 * @method static void createMetadataUsing(callable $callback)
 * @method static void commitImmediately(bool $commit_immediately = true)
 * @method static EventStoreFake assertCommitted(string|Closure $event, Closure|int|null $callback = null)
 * @method static EventStoreFake assertNotCommitted(string|Closure $event, ?Closure $callback = null)
 * @method static EventStoreFake assertNothingCommitted()
 * @method static CarbonInterface realNow()
 */
class Verbs extends Facade
{
    public static function fake()
    {
        return app(BrokerStore::class)->use('fake')->current();
    }

    public static function standalone()
    {
        return app(BrokerStore::class)->use('standalone')->current();
    }

    public static function broker()
    {
        return static::getFacadeRoot();
    }

    public static function getFacadeRoot(): BrokersEvents
    {
        return app(BrokerStore::class)->current();
    }

    protected static function getFacadeAccessor()
    {
        return BrokersEvents::class;
    }
}
