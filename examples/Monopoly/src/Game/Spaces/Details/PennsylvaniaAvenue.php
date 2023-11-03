<?php

namespace Thunk\Verbs\Examples\Monopoly\Game\Spaces\Details;

use Thunk\Verbs\Examples\Monopoly\Game\PropertyColor;
use Thunk\Verbs\Examples\Monopoly\Game\Spaces\PropertyDetails;

class PennsylvaniaAvenue extends PropertyDetails
{
    public string $name = 'Pennsylvania Avenue';

    public PropertyColor $color = PropertyColor::Green;

    public int $position = 34;

    public int $price = 320;

    /** @var int[] */
    public array $rent = [28, 150, 450, 1000, 1200, 1400];

    public int $building_cost = 200;
}
