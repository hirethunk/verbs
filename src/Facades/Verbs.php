<?php

namespace Thunk\Verbs\Facades;

use Carbon\CarbonInterface;
use Closure;
use Illuminate\Support\Facades\Facade;
use Thunk\Verbs\Contracts\BrokersEvents;
use Thunk\Verbs\Event;
use Thunk\Verbs\Lifecycle\Phase;
use Thunk\Verbs\Lifecycle\BrokerBuilder;
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
 * @method static void skipPhases(Phase ...$phases)
 */
class Verbs extends Facade
{
    public static function fake()
    {
        if (! app(BrokerStore::class)->has('fake')) {
            app(BrokerStore::class)->register('fake', BrokerBuilder::fake());
        }

        return app(BrokerStore::class)->swap('fake')->current();
    }

    public static function standalone()
    {
        if (! app(BrokerStore::class)->has('standalone')) {
            app(BrokerStore::class)->register('standalone', BrokerBuilder::standalone());
        }

        return app(BrokerStore::class)->swap('standalone')->current();
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
