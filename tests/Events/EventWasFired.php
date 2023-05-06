<?php

namespace Thunk\Verbs\Tests\Events;

use Thunk\Verbs\Event;

class EventWasFired extends Event
{
    public function __construct(
        public string $name
    ) {
    }
}
