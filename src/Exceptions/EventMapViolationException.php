<?php

namespace Thunk\Verbs\Exceptions;

use RuntimeException;
use Thunk\Verbs\Event;

class EventMapViolationException extends RuntimeException
{
    /**
     * The name of the affected event.
     */
    public string $event;

    /**
     * Create a new exception instance.
     *
     * @param  class-string<Event>  $event
     */
    public function __construct(string $event)
    {
        parent::__construct("No alias defined for event [{$event}].");

        $this->event = $event;
    }
}
