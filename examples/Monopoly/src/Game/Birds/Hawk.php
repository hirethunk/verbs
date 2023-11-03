<?php

namespace Thunk\Verbs\Examples\Monopoly\Game\Birds;

use Thunk\Verbs\Examples\Monopoly\Game\Food;
use Thunk\Verbs\Examples\Monopoly\Game\Habitat;

class Hawk extends Bird
{
    public array $cost = [Food::Mouse];

    public int $points = 4;

    public Habitat $habitat = Habitat::Trees;
}
