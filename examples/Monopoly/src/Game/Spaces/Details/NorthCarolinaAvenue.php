<?php

namespace Thunk\Verbs\Examples\Monopoly\Game\Spaces\Details;

use Thunk\Verbs\Examples\Monopoly\Game\PropertyColor;
use Thunk\Verbs\Examples\Monopoly\Game\Spaces\PropertyDetails;

class NorthCarolinaAvenue extends PropertyDetails
{
    public string $name = 'North Carolina Avenue';

    public PropertyColor $color = PropertyColor::Green;

    public int $position = 32;

    public int $price = 300;

    /** @var int[] */
    public array $rent = [26, 130, 390, 900, 1100, 1275];

    public int $building_cost = 200;
}
