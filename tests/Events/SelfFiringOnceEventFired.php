<?php

namespace Thunk\Verbs\Tests\Events;

use Thunk\Verbs\Attributes\Once;
use Thunk\Verbs\Event;

class SelfFiringOnceEventFired extends Event
{
    public function __construct(
        public string $name
    ) {
    }

    #[Once]
    public function onFire()
    {
        $GLOBALS['heard_events'][] = "self-once:{$this->name}";
    }
}
