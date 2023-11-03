<?php

namespace Thunk\Verbs\Examples\Monopoly\Game\Birds;

use Thunk\Verbs\Examples\Monopoly\Game\Food;
use Thunk\Verbs\Examples\Monopoly\Game\Habitat;

class Nuthatch extends Bird
{
    public array $cost = [Food::Wheat];

    public int $points = 2;

    public Habitat $habitat = Habitat::Water;
}
