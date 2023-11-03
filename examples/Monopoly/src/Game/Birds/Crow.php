<?php

namespace Thunk\Verbs\Examples\Monopoly\Game\Birds;

use Thunk\Verbs\Examples\Monopoly\Game\Food;
use Thunk\Verbs\Examples\Monopoly\Game\Habitat;

class Crow extends Bird
{
    public array $cost = [Food::Wheat, Food::Berries];

    public int $points = 5;

    public Habitat $habitat = Habitat::Grass;
}
