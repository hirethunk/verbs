<?php

namespace Thunk\Verbs\Examples\Monopoly\Game\Spaces\Details;

use Thunk\Verbs\Examples\Monopoly\Game\PropertyColor;
use Thunk\Verbs\Examples\Monopoly\Game\Spaces\PropertyDetails;

class ParkPlace extends PropertyDetails
{
    protected string $name = 'Park Place';

    protected PropertyColor $color = PropertyColor::Blue;

    protected int $position = 37;

    protected int $price = 350;

    /** @var int[] */
    protected array $rent = [35, 175, 500, 1100, 1300, 1500];

    protected int $building_cost = 200;
}