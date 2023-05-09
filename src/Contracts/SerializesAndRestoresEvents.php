<?php

namespace Thunk\Verbs\Contracts;

use Thunk\Verbs\Event;

interface SerializesAndRestoresEvents
{
    public function serializeEvent(Event $event): string;

    public function deserializeEvent(string $event_type, string $data): Event;
}
