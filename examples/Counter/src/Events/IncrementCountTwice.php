<?php

namespace Thunk\Verbs\Examples\Counter\Events;

use Thunk\Verbs\Attributes\Autodiscovery\AppliesToSingletonState;
use Thunk\Verbs\Event;
use Thunk\Verbs\Examples\Counter\States\CountState;

#[AppliesToSingletonState(CountState::class)]
class IncrementCountTwice extends Event
{
    public function handle()
    {
        IncrementCount::fire();
        IncrementCount::fire();
    }
}
