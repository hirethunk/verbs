<?php

namespace Thunk\Verbs\Examples\Monopoly\Game\Spaces\Spaces\Properties;

use Thunk\Verbs\Examples\Monopoly\Game\PropertyColor;
use Thunk\Verbs\Examples\Monopoly\Game\Spaces\Property;

class IndianaAvenue extends Property
{
    protected string $name = 'Indiana Avenue';

    protected PropertyColor $color = PropertyColor::Red;

    protected int $position = 23;

    protected int $price = 220;

    /** @var int[] */
    protected array $rent = [18, 90, 250, 700, 875, 1050];

    protected int $building_cost = 150;
}
