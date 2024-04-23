<?php

namespace Thunk\Verbs\Testing;

use Closure;
use Illuminate\Contracts\Container\Container;
use PHPUnit\Framework\Assert;
use Thunk\Verbs\Contracts\BrokersEvents;
use Thunk\Verbs\Contracts\StoresEvents;
use Thunk\Verbs\Event;
use Thunk\Verbs\Lifecycle\Broker;
use Thunk\Verbs\Lifecycle\BrokerConvenienceMethods;

class BrokerFake extends Broker
{
    use BrokerConvenienceMethods;

    protected int $commit_call_count = 0;

    // public function __construct(
    //     Container $container,
    //     public EventStoreFake $store,
    //     public Broker $broker,
    // ) {
    //     // Eventually this will swap out all the necessary fakes and implement
    //     // our own versions of fire/commit/replay, but for now this is just a
    //     // placeholder class.
    //     //
    //     //  - [x] EventStore
    //     //  - [ ] EventQueue
    //     //  - [ ] SnapshotStore
    //     //  - [ ] StateManager (might only need to fake this instead of snapshot store)

    //     $container->instance(StoresEvents::class, $this->store);
    // }

    public function assertCommitted(string|Closure $event, Closure|int|null $callback = null): EventStoreFake
    {
        return $this->event_store->assertCommitted($event, $callback);
    }

    public function assertNotCommitted(string|Closure $event, ?Closure $callback = null): EventStoreFake
    {
        return $this->event_store->assertNotCommitted($event, $callback);
    }

    public function assertNothingCommitted(): EventStoreFake
    {
        return $this->event_store->assertNothingCommitted();
    }

    public function assertCommitCalledTimes(int $times): EventStoreFake
    {
        Assert::assertEquals(
            expected: $times,
            actual: $this->commit_call_count,
            message: "Expected commit to be called {$times} time(s), but was called {$this->commit_call_count} time(s)."
        );

        return $this->event_store;
    }

    // public function fire(Event $event): ?Event
    // {
    //     return $this->broker->fire($event);
    // }

    public function commit(): bool
    {
        $this->commit_call_count++;

        return parent::commit();
    }

    // public function replay(?callable $beforeEach = null, ?callable $afterEach = null)
    // {
    //     $this->broker->replay($beforeEach, $afterEach);
    // }

    // public function commitImmediately(bool $commit_immediately = true): void
    // {
    //     $this->broker->commitImmediately($commit_immediately);
    // }
}
