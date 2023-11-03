<?php

namespace Thunk\Verbs\Examples\Monopoly\Game\Spaces\Details;

use Thunk\Verbs\Examples\Monopoly\Game\PropertyColor;
use Thunk\Verbs\Examples\Monopoly\Game\Spaces\PropertyDetails;

class MarvinGardens extends PropertyDetails
{
    public string $name = 'Marvin Gardens';

    public PropertyColor $color = PropertyColor::Yellow;

    public int $position = 29;

    public int $price = 280;

    /** @var int[] */
    public array $rent = [24, 120, 360, 850, 1025, 1200];

    public int $building_cost = 150;
}
