<?php

namespace Thunk\Verbs\Attributes\Hooks;

use Attribute;
use InvalidArgumentException;
use Thunk\Verbs\Event;
use Thunk\Verbs\Lifecycle\Hook;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Listen implements HookAttribute
{
    public function __construct(
        protected string $event_type
    ) {
        if (! is_a($this->event_type, Event::class, true)) {
            throw new InvalidArgumentException('You must pass event class names to the "Listen" attribute.');
        }
    }

    public function applyToHook(Hook $hook): void
    {
        $hook->targets[] = $this->event_type;
    }
}
