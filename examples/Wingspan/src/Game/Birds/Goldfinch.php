<?php

namespace Thunk\Verbs\Examples\Wingspan\Game\Birds;

use Thunk\Verbs\Examples\Wingspan\Game\Food;

class Goldfinch extends Bird
{
    public array $cost = [Food::Berries];

    public int $points = 2;
}
