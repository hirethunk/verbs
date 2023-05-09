<?php

namespace Thunk\Verbs\Testing;

use Exception;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Testing\Fakes\Fake;
use PHPUnit\Framework\Assert;
use Thunk\Verbs\Contracts\StoresEvents;
use Thunk\Verbs\Event;
use Thunk\Verbs\Facades\Snowflake;
use Thunk\Verbs\Snowflakes\Snowflake as SnowflakeInstance;

class EventRepositoryFake implements StoresEvents, Fake
{
    protected array $saved = [];

    public function assertSaved(string $event_type)
    {
        Assert::assertTrue($this->get([$event_type])->isNotEmpty());
    }

    public function assertNothingSaved()
    {
        Assert::assertEmpty($this->saved);
    }

    public function save(Event $event): SnowflakeInstance
    {
        $this->saved[] = $event;

        return Snowflake::make();
    }

    /** @return LazyCollection<int, \Thunk\Verbs\Event> */
    public function get(
        ?array $event_types = null,
        ?SnowflakeInstance $context_id = null,
        ?SnowflakeInstance $after = null,
        int $chunk_size = 1000,
    ): LazyCollection {
        return LazyCollection::make($this->saved)
            ->when($after, fn ($collection) => throw new Exception('"after" not implemented on fake.'))
            ->when($context_id, fn ($collection) => $collection->filter(fn (Event $event) => $event->context_id->is($context_id)))
            ->when($event_types, fn ($collection) => $collection->filter(fn (Event $event) => in_array($event::class, $event_types)));
    }
}
