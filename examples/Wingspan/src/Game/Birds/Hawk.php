<?php

namespace Thunk\Verbs\Examples\Wingspan\Game\Birds;

use Thunk\Verbs\Examples\Wingspan\Game\Food;

class Hawk extends Bird
{
    public array $cost = [Food::Mouse];

    public int $points = 4;
}
