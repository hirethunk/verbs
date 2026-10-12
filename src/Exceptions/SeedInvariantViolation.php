<?php

namespace Thunk\Verbs\Exceptions;

use RuntimeException;
use Thunk\Verbs\Event;
use Thunk\Verbs\State;

class SeedInvariantViolation extends RuntimeException
{
    public function __construct(
        public Event $event,
        public State $state,
    ) {
        parent::__construct(sprintf(
            'State [%s:%s] is already at or past event [%s] during a seeded rebuild.',
            $state::class,
            $state->id,
            $event->id,
        ));
    }
}
