<?php

namespace Thunk\Verbs\Examples\Counter\Events;

use Thunk\Verbs\Attributes\Autodiscovery\AppliesToState;
use Thunk\Verbs\Event;
use Thunk\Verbs\Examples\Counter\States\CountState;

/**
 * In our most basic example of event sourcing, we just use a single event
 * to increment a counter. Because CountState is a SingletonState object,
 * Verbs will always apply this event to a single `CountState` across the
 * entire application (as opposed to having different counts for different
 * situations).
 *
 * Because we're using a singleton state, there is no need for the event to
 * have a `$count_id`.
 */
#[AppliesToState(CountState::class)]
class IncrementCount extends Event
{
    public function apply(CountState $state)
    {
        $state->count++;
    }
}
