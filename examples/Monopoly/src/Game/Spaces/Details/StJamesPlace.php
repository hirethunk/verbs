<?php

namespace Thunk\Verbs\Examples\Monopoly\Game\Spaces\Details;

use Thunk\Verbs\Examples\Monopoly\Game\PropertyColor;
use Thunk\Verbs\Examples\Monopoly\Game\Spaces\PropertyDetails;

class StJamesPlace extends PropertyDetails
{
    public string $name = 'St. James Place';

    public PropertyColor $color = PropertyColor::Orange;

    public int $position = 16;

    public int $price = 180;

    /** @var int[] */
    public array $rent = [14, 70, 200, 550, 750, 950];

    public int $building_cost = 100;
}
