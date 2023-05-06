<?php

namespace Thunk\Verbs\Testing;

use Illuminate\Support\Testing\Fakes\Fake;
use PHPUnit\Framework\Assert;
use Thunk\Verbs\Contracts\Bus as BusContract;
use Thunk\Verbs\Event;

class BusFake implements BusContract, Fake
{
    protected array $registered = [];

    protected array $dispatched = [];

    protected array $replayed = [];

    public function assertRegistered(string $listener_type)
    {
        Assert::assertContains($listener_type, $this->registered);
    }

    public function assertDispatched(string $event_type)
    {
        Assert::assertContains($event_type, $this->dispatched);
    }

    public function assertNothingDispatched()
    {
        Assert::assertEmpty($this->dispatched);
    }

    public function assertReplayed(string $event_type)
    {
        Assert::assertContains($event_type, $this->replayed);
    }

    public function assertNothingReplayed()
    {
        Assert::assertEmpty($this->replayed);
    }

    public function assertNothingDispatchedOrReplayed()
    {
        static::assertNothingDispatched();
        static::assertNothingReplayed();
    }

    public function listen(object $listener): void
    {
        $this->registered[] = $listener::class;
    }

    public function dispatch(Event $event): void
    {
        $this->dispatched[] = $event::class;
    }

    public function replay(Event $event): void
    {
        $this->replayed[] = $event::class;
    }
}
