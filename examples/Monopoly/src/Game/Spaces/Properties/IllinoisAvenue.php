<?php

namespace Thunk\Verbs\Examples\Monopoly\Game\{Spaces\Properties};

use Thunk\Verbs\Examples\Monopoly\Game\PropertyColor;
use Thunk\Verbs\Examples\Monopoly\Game\Spaces\Property;

class IllinoisAvenue extends Property
{
    protected string $name = 'Illinois Avenue';

    protected PropertyColor $color = PropertyColor::Red;

    protected int $position = 24;

    protected int $price = 240;

    /** @var int[] */
    protected array $rent = [20, 100, 300, 750, 925, 1100];

    protected int $building_cost = 150;
}