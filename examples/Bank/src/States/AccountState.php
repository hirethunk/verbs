<?php

namespace Thunk\Verbs\Examples\Bank\States;

use Thunk\Verbs\State;

class AccountState extends State
{
    public int $balance_in_cents = 0;
}
