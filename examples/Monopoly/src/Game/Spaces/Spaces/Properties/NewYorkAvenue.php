<?php

namespace Thunk\Verbs\Examples\Monopoly\Game\Spaces\Spaces\Properties;

use Thunk\Verbs\Examples\Monopoly\Game\PropertyColor;
use Thunk\Verbs\Examples\Monopoly\Game\Spaces\Property;

class NewYorkAvenue extends Property
{
    protected string $name = 'New York Avenue';

    protected PropertyColor $color = PropertyColor::Orange;

    protected int $position = 19;

    protected int $price = 200;

    /** @var int[] */
    protected array $rent = [16, 80, 220, 600, 800, 1000];

    protected int $building_cost = 100;
}
