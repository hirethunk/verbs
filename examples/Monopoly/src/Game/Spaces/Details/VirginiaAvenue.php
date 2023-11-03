<?php

namespace Thunk\Verbs\Examples\Monopoly\Game\Spaces\Details;

use Thunk\Verbs\Examples\Monopoly\Game\PropertyColor;
use Thunk\Verbs\Examples\Monopoly\Game\Spaces\PropertyDetails;

class VirginiaAvenue extends PropertyDetails
{
    public string $name = 'Virginia Avenue';

    public PropertyColor $color = PropertyColor::Pink;

    public int $position = 14;

    public int $price = 160;

    /** @var int[] */
    public array $rent = [12, 60, 180, 500, 700, 900];

    public int $building_cost = 100;
}
