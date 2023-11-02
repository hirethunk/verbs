<?php

namespace Thunk\Verbs\Examples\Wingspan\Game\Birds;

use Illuminate\Support\Collection;
use Thunk\Verbs\Examples\Wingspan\Game\Food;
use Thunk\Verbs\Examples\Wingspan\Game\Habitat;

class Crow extends Bird
{
    public array $cost = [Food::Wheat, Food::Berries];

    public int $points = 5;

    public Habitat $habitat = Habitat::Grass;
}
