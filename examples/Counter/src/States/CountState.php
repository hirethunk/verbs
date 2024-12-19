<?php

namespace Thunk\Verbs\Examples\Counter\States;

use Thunk\Verbs\SingletonState;

class CountState extends SingletonState
{
    public int $count = 0;
}
