<?php

namespace Thunk\Verbs\Examples\Wingspan\Game\Birds;

use Thunk\Verbs\Examples\Wingspan\Game\Food;

class Nuthatch extends Bird
{
    public array $cost = [Food::Wheat];

    public int $points = 2;
}
