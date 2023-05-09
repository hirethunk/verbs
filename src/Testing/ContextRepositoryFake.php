<?php

namespace Thunk\Verbs\Testing;

use Illuminate\Support\Testing\Fakes\Fake;
use PHPUnit\Framework\Assert;
use Thunk\Verbs\Context;
use Thunk\Verbs\Contracts\ManagesContext;
use Thunk\Verbs\Event;

class ContextRepositoryFake implements ManagesContext, Fake
{
    protected array $registered = [];

    protected array $synced = [];

    public function assertSynced(string $event_type)
    {
        Assert::assertContains($event_type, $this->synced);
    }

    public function assertNothingSynced()
    {
        Assert::assertEmpty($this->synced);
    }

    public function register(Context $context): Context
    {
        $this->registered[] = $context;

        return $context;
    }

    public function validate(Context $context, Event $event): void
    {
        // TODO
    }

    public function sync(Context $context): Context
    {
        $this->synced[] = $context::class;

        return $context;
    }
}
