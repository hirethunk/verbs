<?php

namespace Thunk\Verbs\Examples\Monopoly\Game\Spaces\Details;

use Thunk\Verbs\Examples\Monopoly\Game\PropertyColor;
use Thunk\Verbs\Examples\Monopoly\Game\Spaces\PropertyDetails;

class IndianaAvenue extends PropertyDetails
{
    public string $name = 'Indiana Avenue';

    public PropertyColor $color = PropertyColor::Red;

    public int $position = 23;

    public int $price = 220;

    /** @var int[] */
    public array $rent = [18, 90, 250, 700, 875, 1050];

    public int $building_cost = 150;
}
