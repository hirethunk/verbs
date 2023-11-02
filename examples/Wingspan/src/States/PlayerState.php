<?php

namespace Thunk\Verbs\Examples\Wingspan\States;

use Thunk\Verbs\State;

class PlayerState extends State
{
    public bool $setup = false;

    public bool $first_player = false;

    public int $available_action_cubes = 0;
}
