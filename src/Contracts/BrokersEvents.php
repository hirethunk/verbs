<?php

namespace Thunk\Verbs\Contracts;

use Thunk\Verbs\Event;

interface BrokersEvents
{
    public function fire(Event $event): void;

    public function replay(array|string $event_types = null, int $chunk_size = 1000): void;
}
