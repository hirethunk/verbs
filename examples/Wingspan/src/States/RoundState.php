<?php

namespace Thunk\Verbs\Examples\Wingspan\States;

use Thunk\Verbs\State;

class RoundState extends State
{
    public int $number;

    public function isFinished(): bool
    {
        // TODO: Check that all actions have been completed

        return false;
    }
}
