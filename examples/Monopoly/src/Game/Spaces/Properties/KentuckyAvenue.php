<?php

namespace Thunk\Verbs\Examples\Monopoly\Game\Spaces\Properties;

use Thunk\Verbs\Examples\Monopoly\Game\PropertyColor;
use Thunk\Verbs\Examples\Monopoly\Game\Spaces\Property;

class KentuckyAvenue extends Property
{
    protected string $name = 'Kentucky Avenue';

    protected PropertyColor $color = PropertyColor::Red;

    protected int $position = 21;

    protected int $price = 220;

    /** @var int[] */
    protected array $rent = [18, 90, 250, 700, 875, 1050];

    protected int $building_cost = 150;
}
