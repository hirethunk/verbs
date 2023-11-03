<?php

namespace Thunk\Verbs\Examples\Monopoly\Game\Spaces\Details;

use Thunk\Verbs\Examples\Monopoly\Game\PropertyColor;
use Thunk\Verbs\Examples\Monopoly\Game\Spaces\PropertyDetails;

class Boardwalk extends PropertyDetails
{
    public string $name = 'Boardwalk';

    public PropertyColor $color = PropertyColor::Blue;

    public int $position = 39;

    public int $price = 400;

    /** @var int[] */
    public array $rent = [50, 200, 600, 1400, 1700, 2000];

    public int $building_cost = 200;
}
