<?php

namespace Thunk\Verbs\Examples\Monopoly\Game\{Spaces\Properties};

use Thunk\Verbs\Examples\Monopoly\Game\PropertyColor;
use Thunk\Verbs\Examples\Monopoly\Game\Spaces\Property;

class AtlanticAvenue extends Property
{
    protected string $name = 'Atlantic Avenue';

    protected PropertyColor $color = PropertyColor::Yellow;

    protected int $position = 26;

    protected int $price = 260;

    /** @var int[] */
    protected array $rent = [22, 110, 330, 800, 975, 1150];

    protected int $building_cost = 150;
}