<?php

namespace Thunk\Verbs\Examples\Wingspan\Game\Birds;

use Illuminate\Support\Collection;
use Thunk\Verbs\Examples\Wingspan\Game\Food;

class Crow extends Bird
{
    public array $cost = [Food::Wheat, Food::Berries];

    public int $points = 5;
}
