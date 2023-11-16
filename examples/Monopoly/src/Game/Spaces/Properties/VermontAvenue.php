<?php

namespace Thunk\Verbs\Examples\Monopoly\Game\Spaces\Properties;

use Thunk\Verbs\Examples\Monopoly\Game\PropertyColor;
use Thunk\Verbs\Examples\Monopoly\Game\Spaces\Property;

class VermontAvenue extends Property
{
    protected string $name = 'Vermont Avenue';

    protected PropertyColor $color = PropertyColor::LightBlue;

    protected int $position = 8;

    protected int $price = 100;

    /** @var int[] */
    protected array $rent = [6, 30, 90, 270, 400, 550];

    protected int $building_cost = 50;
}
