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
use Thunk\Verbs\Lifecycle\ReplayMode;
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
        // Faking twice is a no-op: the fake world—and everything already
        // committed to it—survives repeated calls.
        if (($faked = static::getFacadeRoot()) instanceof BrokerFake) {
            return $faked;
        }

        $app = static::getFacadeApplication();

        $app->instance(StoresEvents::class, $store = $app->make(EventStoreFake::class));
        $app->instance(StoresSnapshots::class, $snapshots = $app->make(SnapshotStoreFake::class));

        // fake() starts a fresh Verbs world. The scoped services were
        // constructed against the real stores, so entering the fake world
        // rebuilds them—fresh scopes whose constructor injections pick up the
        // fakes—which deliberately discards anything in flight: queued-but-
        // uncommitted events never reach the fake stores, and state references
        // loaded before the fake are no longer canonical. The registry reset
        // drops the state instances it resolved against the old scope (its
        // reflection metadata is scope-independent and kept). Everything else
        // resolves the broker lazily and follows the swap() below, which also
        // rebinds the container's [BrokersEvents] instance.
        $app->forgetInstance(StateManager::class);
        $app->forgetInstance(AutoCommitManager::class);
        $app->forgetInstance(EventQueue::class);
        $app->forgetInstance(ReplayMode::class);
        $app->forgetInstance(Broker::class);
        $app->make(EventStateRegistry::class)->reset();

        $fake_broker = new BrokerFake($store, $snapshots, $app->make(Broker::class), $app->make(ReplayMode::class));

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
