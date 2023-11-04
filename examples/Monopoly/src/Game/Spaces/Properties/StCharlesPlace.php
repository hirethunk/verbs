<?php

namespace Thunk\Verbs\Examples\Monopoly\Game\{Spaces\Properties};

use Thunk\Verbs\Examples\Monopoly\Game\PropertyColor;
use Thunk\Verbs\Examples\Monopoly\Game\Spaces\Property;

class StCharlesPlace extends Property
{
    protected string $name = 'St. Charles Place';

    protected PropertyColor $color = PropertyColor::Pink;

    protected int $position = 11;

    protected int $price = 140;

    /** @var int[] */
    protected array $rent = [10, 50, 150, 450, 625, 750];

    protected int $building_cost = 100;
}