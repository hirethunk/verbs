<?php

namespace Thunk\Verbs\Testing;

use Closure;
use PHPUnit\Framework\Assert;
use Thunk\Verbs\Contracts\BrokersEvents;
use Thunk\Verbs\Event;
use Thunk\Verbs\Lifecycle\BrokerConvenienceMethods;
use Thunk\Verbs\Lifecycle\Dispatcher;
use Thunk\Verbs\Lifecycle\ReplayMode;

class BrokerFake implements BrokersEvents
{
    use BrokerConvenienceMethods;

    protected int $commit_call_count = 0;

    // Eventually this will implement its own versions of fire/commit/replay
    // against the fakes, but for now it wraps a real broker that Verbs::fake()
    // rebuilt against the fake stores.
    //
    //  - [x] EventStore
    //  - [ ] EventQueue
    //  - [x] SnapshotStore
    //  - [ ] StateManager
    public function __construct(
        public EventStoreFake $store,
        public SnapshotStoreFake $snapshots,
        protected BrokersEvents $broker,
        protected ReplayMode $replay_mode,
    ) {}

    public function assertCommitted(string|Closure $event, Closure|int|null $callback = null): EventStoreFake
    {
        return $this->store->assertCommitted($event, $callback);
    }

    public function assertNotCommitted(string|Closure $event, ?Closure $callback = null): EventStoreFake
    {
        return $this->store->assertNotCommitted($event, $callback);
    }

    public function assertNothingCommitted(): EventStoreFake
    {
        return $this->store->assertNothingCommitted();
    }

    public function assertCommitCalledTimes(int $times): EventStoreFake
    {
        Assert::assertEquals(
            expected: $times,
            actual: $this->commit_call_count,
            message: "Expected commit to be called {$times} time(s), but was called {$this->commit_call_count} time(s)."
        );

        return $this->store;
    }

    public function fire(Event $event): ?Event
    {
        return $this->broker->fire($event);
    }

    public function commit(): bool
    {
        $this->commit_call_count++;

        return $this->broker->commit();
    }

    public function replay(?callable $beforeEach = null, ?callable $afterEach = null)
    {
        $this->broker->replay($beforeEach, $afterEach);
    }

    public function listen(object|string $listener)
    {
        app(Dispatcher::class)->register($listener);
    }

    public function commitImmediately(bool $commit_immediately = true): void
    {
        $this->broker->commitImmediately($commit_immediately);
    }
}
