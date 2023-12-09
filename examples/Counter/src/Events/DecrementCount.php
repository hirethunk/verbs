<?php

namespace Thunk\Verbs\Examples\Counter\Events;

use Thunk\Verbs\Attributes\Autodiscovery\AppliesToSingletonState;
use Thunk\Verbs\Event;
use Thunk\Verbs\Examples\Counter\States\CountState;

#[AppliesToSingletonState(CountState::class)]
class DecrementCount extends Event
{
    public function apply(CountState $state)
    {
        $state->count--;
    }

    public function handle()
    {
        if ($this->state(CountState::class)->count < 0) {
            ResetCount::fire();
        }
    }
}
