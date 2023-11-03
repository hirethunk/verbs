<?php

namespace Thunk\Verbs\Examples\Monopoly\Game\Spaces\Details;

use Thunk\Verbs\Examples\Monopoly\Game\PropertyColor;
use Thunk\Verbs\Examples\Monopoly\Game\Spaces\PropertyDetails;

class AtlanticAvenue extends PropertyDetails
{
    public string $name = 'Atlantic Avenue';

    public PropertyColor $color = PropertyColor::Yellow;

    public int $position = 26;

    public int $price = 260;

    /** @var int[] */
    public array $rent = [22, 110, 330, 800, 975, 1150];

    public int $building_cost = 150;
}
