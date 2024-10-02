<?php

namespace Thunk\Verbs\Examples\Counter\Events;

use Thunk\Verbs\Attributes\Autodiscovery\AppliesToState;
use Thunk\Verbs\Event;
use Thunk\Verbs\Examples\Counter\States\CountState;

#[AppliesToState(CountState::class)]
class IncrementCountTwice extends Event
{
    public function handle()
    {
        IncrementCount::fire();
        IncrementCount::fire();
    }
}
