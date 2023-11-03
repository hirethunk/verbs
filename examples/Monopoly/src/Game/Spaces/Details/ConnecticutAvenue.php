<?php

namespace Thunk\Verbs\Examples\Monopoly\Game\Spaces\Details;

use Thunk\Verbs\Examples\Monopoly\Game\PropertyColor;
use Thunk\Verbs\Examples\Monopoly\Game\Spaces\PropertyDetails;

class ConnecticutAvenue extends PropertyDetails
{
    public string $name = 'Connecticut Avenue';

    public PropertyColor $color = PropertyColor::LightBlue;

    public int $position = 9;

    public int $price = 120;

    /** @var int[] */
    public array $rent = [8, 40, 100, 300, 450, 600];

    public int $building_cost = 50;
}
