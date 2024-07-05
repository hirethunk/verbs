<?php

namespace Thunk\Verbs\Facades;

use Carbon\CarbonInterface;
use Closure;
use Illuminate\Support\Facades\Facade;
use Thunk\Verbs\Contracts\BrokersEvents;
use Thunk\Verbs\Event;
use Thunk\Verbs\Lifecycle\Phase;
use Thunk\Verbs\Testing\BrokerFake;
use Thunk\Verbs\Testing\EventStoreFake;

/**
 * @method static bool commit()
 * @method static bool isReplaying()
 * @method static void unlessReplaying(callable $callback)
 * @method static Event fire(Event $event)
 * @method static void listen(string|object $listener)
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
        $real_broker = static::isFake()
            ? static::getFacadeRoot()->broker
            : static::getFacadeRoot();

        $fake_broker = new BrokerFake(
            static::getFacadeApplication(),
            static::getFacadeApplication()->make(EventStoreFake::class),
            $real_broker
        );

        static::swap($fake_broker);

        return $fake_broker;
    }

    public static function getFacadeRoot(): BrokersEvents
    {
        return parent::getFacadeRoot();
    }

    protected static function getFacadeAccessor()
    {
        return BrokersEvents::class;
    }
}
