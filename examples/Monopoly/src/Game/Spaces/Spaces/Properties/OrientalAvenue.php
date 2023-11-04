<?php

namespace Thunk\Verbs\Examples\Monopoly\Game\Spaces\Spaces\Properties;

use Thunk\Verbs\Examples\Monopoly\Game\PropertyColor;
use Thunk\Verbs\Examples\Monopoly\Game\Spaces\Property;

class OrientalAvenue extends Property
{
    protected string $name = 'Oriental Avenue';

    protected PropertyColor $color = PropertyColor::LightBlue;

    protected int $position = 6;

    protected int $price = 100;

    /** @var int[] */
    protected array $rent = [6, 30, 90, 270, 400, 550];

    protected int $building_cost = 50;
}