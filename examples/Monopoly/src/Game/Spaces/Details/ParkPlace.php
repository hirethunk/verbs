<?php

namespace Thunk\Verbs\Examples\Monopoly\Game\Spaces\Details;

use Thunk\Verbs\Examples\Monopoly\Game\PropertyColor;
use Thunk\Verbs\Examples\Monopoly\Game\Spaces\PropertyDetails;

class ParkPlace extends PropertyDetails
{
    public string $name = 'Park Place';

    public PropertyColor $color = PropertyColor::Blue;

    public int $position = 37;

    public int $price = 350;

    /** @var int[] */
    public array $rent = [35, 175, 500, 1100, 1300, 1500];

    public int $building_cost = 200;
}
