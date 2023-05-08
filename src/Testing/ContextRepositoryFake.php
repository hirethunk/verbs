<?php

namespace Thunk\Verbs\Testing;

use BadMethodCallException;
use Illuminate\Support\Testing\Fakes\Fake;
use PHPUnit\Framework\Assert;
use Thunk\Verbs\Context;
use Thunk\Verbs\Contracts\ManagesContext;
use Thunk\Verbs\Event;
use Thunk\Verbs\Snowflakes\Snowflake;

class ContextRepositoryFake implements ManagesContext, Fake
{
    protected array $applied = [];

    public function assertApplied(string $event_type)
    {
        Assert::assertContains($event_type, $this->applied);
    }

    public function assertNothingSaved()
    {
        Assert::assertEmpty($this->applied);
    }

    public function apply(Event $event): void
    {
        $this->applied[] = $event::class;
    }

    public function get(string $class_name, Snowflake $id): Context
    {
        throw new BadMethodCallException('Cannot get context from fake.');
    }
}
