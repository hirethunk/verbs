<?php

namespace Thunk\Verbs\Examples\Counter\Events;

use Thunk\Verbs\Attributes\Autodiscovery\AppliesToSingletonState;
use Thunk\Verbs\Event;
use Thunk\Verbs\Examples\Counter\States\CountState;

/**
 * This is a very **basic** example.
 */

#[AppliesToSingletonState(CountState::class)]
class IncrementCount extends Event
{
    public function apply(CountState $state)
    {
        $state->count++;
    }
}
