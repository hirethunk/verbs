<?php

namespace Thunk\Verbs\Examples\Monopoly\Game\Spaces\Properties;

use Thunk\Verbs\Examples\Monopoly\Game\PropertyColor;
use Thunk\Verbs\Examples\Monopoly\Game\Spaces\Property;

class PacificAvenue extends Property
{
    protected string $name = 'Pacific Avenue';

    protected PropertyColor $color = PropertyColor::Green;

    protected int $position = 31;

    protected int $price = 300;

    /** @var int[] */
    protected array $rent = [26, 130, 390, 900, 1100, 1275];

    protected int $building_cost = 200;
}
