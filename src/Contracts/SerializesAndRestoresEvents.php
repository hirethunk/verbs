<?php

namespace Thunk\Verbs\Contracts;

use Illuminate\Support\LazyCollection;
use Symfony\Component\Serializer\SerializerInterface;
use Thunk\Verbs\Event;
use Thunk\Verbs\Snowflakes\Snowflake;

interface SerializesAndRestoresEvents
{
    public function serializeEvent(Event $event): string;

    public function deserializeEvent(string $event_type, array $data): Event;
}
