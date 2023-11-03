<?php

namespace Thunk\Verbs\Examples\Wingspan\Game\Birds;

use Thunk\Verbs\Examples\Wingspan\Game\Food;
use Thunk\Verbs\Examples\Wingspan\Game\Habitat;

class Nuthatch extends Bird
{
    public array $cost = [Food::Wheat];

    public int $points = 2;

    public Habitat $habitat = Habitat::Water;
}
