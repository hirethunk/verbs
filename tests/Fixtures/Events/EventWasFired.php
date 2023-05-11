<?php

namespace Thunk\Verbs\Tests\Fixtures\Events;

use Thunk\Verbs\Event;

class EventWasFired extends Event
{
    public function __construct(
        public string $name
    ) {
    }
}
