<?php

namespace Thunk\Verbs\Examples\Monopoly\Game\Birds;

use Thunk\Verbs\Examples\Monopoly\Game\Food;
use Thunk\Verbs\Examples\Monopoly\Game\Habitat;

class BaldEagle extends Bird
{
    public array $cost = [Food::Mouse, Food::Fish];

    public int $points = 8;

    public Habitat $habitat = Habitat::Trees;
}
