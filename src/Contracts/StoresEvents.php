<?php

namespace Thunk\Verbs\Contracts;

use Illuminate\Support\LazyCollection;
use Thunk\Verbs\Event;
use Thunk\Verbs\Snowflakes\Snowflake;

interface StoresEvents
{
    public function save(Event $event): Snowflake;

    public function get(
        ?array $event_types = null,
        ?Snowflake $context_id = null,
        ?Snowflake $after = null,
        int $chunk_size = 1000,
    ): LazyCollection;
}
