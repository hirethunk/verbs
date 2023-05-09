<?php

namespace Thunk\Verbs\Tests\Fixtures\Events;

use Thunk\Verbs\Attributes\CreatesContext;
use Thunk\Verbs\Event;
use Thunk\Verbs\Tests\Fixtures\Contexts\GenericContext;

#[CreatesContext(GenericContext::class)]
class EventCreatedContext extends Event
{
    public function __construct(
        public string $name
    ) {
    }
}
