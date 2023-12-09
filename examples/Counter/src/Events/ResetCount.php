<?php

namespace Thunk\Verbs\Examples\Counter\Events;

use Thunk\Verbs\Event;
use Thunk\Verbs\Examples\Counter\States\CountState;
use Thunk\Verbs\Attributes\Autodiscovery\AppliesToSingletonState;

#[AppliesToSingletonState(CountState::class)]
class ResetCount extends Event
{
    public function apply(CountState $state)
    {
        $state->count = 0;
    }
}
