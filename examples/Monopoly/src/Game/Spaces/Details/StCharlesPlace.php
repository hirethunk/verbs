<?php

namespace Thunk\Verbs\Examples\Monopoly\Game\Spaces\Details;

use Thunk\Verbs\Examples\Monopoly\Game\PropertyColor;
use Thunk\Verbs\Examples\Monopoly\Game\Spaces\PropertyDetails;

class StCharlesPlace extends PropertyDetails
{
    public string $name = 'St. Charles Place';

    public PropertyColor $color = PropertyColor::Pink;

    public int $position = 11;

    public int $price = 140;

    /** @var int[] */
    public array $rent = [10, 50, 150, 450, 625, 750];

    public int $building_cost = 100;
}
