<?php

namespace Thunk\Verbs\Facades;

use Carbon\CarbonInterface;
use Closure;
use Illuminate\Support\Facades\Facade;
use Thunk\Verbs\Contracts\BrokersEvents;
use Thunk\Verbs\Event;
use Thunk\Verbs\Lifecycle\Phase;
use Thunk\Verbs\State;
use Thunk\Verbs\Testing\BrokerFake;
use Thunk\Verbs\Testing\EventStoreFake;

/**
 * Commits all outstanding events
 *
 * @method static bool commit()
 *
 * Determines if verbs is currently replaying events.
 * @method static bool isReplaying()
 *
 * Executes the given callback only if not replaying events.
 * @method static void unlessReplaying(callable $callback)
 *
 * Defers the execution of a callback. It will only get called once per unique constraint.
 * @method static void defer(State|string|iterable|null $unique_by, callable $callback, string $name = 'Default')
 *
 * @param  State|string|int|iterable|null  $unique_by  The uniqueness constraint for the deferred callback. It can be a State, string or array combination of both
 * @param  callable  $callback  The callback to be executed
 * @param  string  $name  Optional name identifier for the deferred callback, defaults to 'Default'. It's a secondary constraint
 *
 * Fires an event through the event store.
 *
 * @method static Event fire(Event $event)
 *
 * @param  Event  $event  The event object to be fired
 *
 * @method static void createMetadataUsing(callable $callback)
 *
 * @param  callable  $callback  The callback function that generates metadata
 * @return Event The fired event instance
 *
 * Sets a callback to create metadata for events.
 *
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
