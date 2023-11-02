<?php

namespace Thunk\Verbs\Examples\Wingspan\Game\Birds;

use Thunk\Verbs\Examples\Wingspan\Game\Food;

class BaldEagle extends Bird
{
    public array $cost = [Food::Mouse, Food::Fish];

    public int $points = 8;
}
