<?php

namespace Thunk\Verbs\Contracts;

use Thunk\Verbs\Context;
use Thunk\Verbs\Event;

interface BrokersEvents
{
    public function originate(Event $event, ?Context $context = null): void;

    public function replay(array|string $event_types = null, int $chunk_size = 1000): void;
}
