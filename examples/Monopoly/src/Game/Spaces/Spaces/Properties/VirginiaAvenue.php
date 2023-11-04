<?php

namespace Thunk\Verbs\Examples\Monopoly\Game\Spaces\Spaces\Properties;

use Thunk\Verbs\Examples\Monopoly\Game\PropertyColor;
use Thunk\Verbs\Examples\Monopoly\Game\Spaces\Property;

class VirginiaAvenue extends Property
{
    protected string $name = 'Virginia Avenue';

    protected PropertyColor $color = PropertyColor::Pink;

    protected int $position = 14;

    protected int $price = 160;

    /** @var int[] */
    protected array $rent = [12, 60, 180, 500, 700, 900];

    protected int $building_cost = 100;
}