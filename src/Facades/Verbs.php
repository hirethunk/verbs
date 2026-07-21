<?php

namespace Thunk\Verbs\Facades;

use Carbon\CarbonInterface;
use Closure;
use Illuminate\Support\Facades\Facade;
use Thunk\Verbs\Contracts\BrokersEvents;
use Thunk\Verbs\Contracts\StoresEvents;
use Thunk\Verbs\Contracts\StoresSnapshots;
use Thunk\Verbs\Event;
use Thunk\Verbs\Lifecycle\AutoCommitManager;
use Thunk\Verbs\Lifecycle\Broker;
use Thunk\Verbs\Lifecycle\Phase;
use Thunk\Verbs\Lifecycle\Queue as EventQueue;
use Thunk\Verbs\State\StateManager;
use Thunk\Verbs\Support\EventStateRegistry;
use Thunk\Verbs\Testing\BrokerFake;
use Thunk\Verbs\Testing\EventStoreFake;
use Thunk\Verbs\Testing\SnapshotStoreFake;

/**
 * @method static bool commit()
 * @method static bool isReplaying()
 * @method static void unlessReplaying(callable $callback)
 * @method static Event fire(Event $event)
 * @method static void replay(?callable $beforeEach = null, ?callable $afterEach = null)
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
        if (($faked = static::getFacadeRoot()) instanceof BrokerFake) {
            return $faked;
        }

        $app = static::getFacadeApplication();

        $app->instance(StoresEvents::class, $store = $app->make(EventStoreFake::class));
        $app->instance(StoresSnapshots::class, $snapshots = $app->make(SnapshotStoreFake::class));

        $app->forgetInstance(StateManager::class);
        $app->forgetInstance(AutoCommitManager::class);
        $app->forgetInstance(EventQueue::class);
        $app->forgetInstance(Broker::class);
        $app->make(EventStateRegistry::class)->reset();

        $fake_broker = new BrokerFake($store, $snapshots, $app->make(Broker::class));

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
