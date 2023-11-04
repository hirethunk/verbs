<?php

namespace Thunk\Verbs\Examples\Monopoly\Game\{Spaces\Properties};

use Thunk\Verbs\Examples\Monopoly\Game\PropertyColor;
use Thunk\Verbs\Examples\Monopoly\Game\Spaces\Property;

class Boardwalk extends Property
{
    protected string $name = 'Boardwalk';

    protected PropertyColor $color = PropertyColor::Blue;

    protected int $position = 39;

    protected int $price = 400;

    /** @var int[] */
    protected array $rent = [50, 200, 600, 1400, 1700, 2000];

    protected int $building_cost = 200;
}