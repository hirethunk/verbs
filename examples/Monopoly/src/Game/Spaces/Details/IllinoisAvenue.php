<?php

namespace Thunk\Verbs\Examples\Monopoly\Game\Spaces\Details;

use Thunk\Verbs\Examples\Monopoly\Game\PropertyColor;
use Thunk\Verbs\Examples\Monopoly\Game\Spaces\PropertyDetails;

class IllinoisAvenue extends PropertyDetails
{
    public string $name = 'Illinois Avenue';

    public PropertyColor $color = PropertyColor::Red;

    public int $position = 24;

    public int $price = 240;

    /** @var int[] */
    public array $rent = [20, 100, 300, 750, 925, 1100];

    public int $building_cost = 150;
}
