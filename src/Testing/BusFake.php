<?php

namespace Thunk\Verbs\Testing;

use Closure;
use Illuminate\Support\Testing\Fakes\Fake;
use Illuminate\Support\Traits\ReflectsClosures;
use PHPUnit\Framework\Assert;
use Thunk\Verbs\Contracts\DispatchesEvents;
use Thunk\Verbs\Event;

class BusFake implements DispatchesEvents, Fake
{
    use ReflectsClosures;

    protected array $registered = [];

    protected array $dispatched = [];

    protected array $replayed = [];

    public function assertRegistered(string $listener_type)
    {
        Assert::assertContains($listener_type, $this->registered);
    }

    public function assertDispatched(string|Closure $event, ?callable $callback = null)
    {
        [$event, $callback] = $this->prepareEventAndCallback($event, $callback);

        $matched = collect($this->dispatched)
            ->filter(fn (Event $dispatched) => $dispatched instanceof $event)
            ->filter($callback);

        Assert::assertTrue($matched->isNotEmpty(), "{$event} was not dispatched");
    }

    public function assertNothingDispatched()
    {
        Assert::assertEmpty($this->dispatched);
    }

    public function assertReplayed(string|Closure $event, ?callable $callback = null)
    {
        [$event, $callback] = $this->prepareEventAndCallback($event, $callback);

        $matched = collect($this->replayed)
            ->filter(fn (Event $dispatched) => $dispatched instanceof $event)
            ->filter($callback);

        Assert::assertTrue($matched->isNotEmpty(), "{$event} was not replayed");
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
        $this->dispatched[] = $event;
    }

    public function replay(Event $event): void
    {
        $this->replayed[] = $event;
    }

    protected function prepareEventAndCallback($event, $callback): array
    {
        if ($event instanceof Closure) {
            return [$this->firstClosureParameterType($event), $event];
        }

        return [$event, $callback];
    }
}
