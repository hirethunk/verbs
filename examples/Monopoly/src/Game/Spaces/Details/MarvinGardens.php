<?php

namespace Thunk\Verbs\Examples\Monopoly\Game\Spaces\Details;

use Thunk\Verbs\Examples\Monopoly\Game\PropertyColor;
use Thunk\Verbs\Examples\Monopoly\Game\Spaces\PropertyDetails;

class MarvinGardens extends PropertyDetails
{
    protected string $name = 'Marvin Gardens';

    protected PropertyColor $color = PropertyColor::Yellow;

    protected int $position = 29;

    protected int $price = 280;

    /** @var int[] */
    protected array $rent = [24, 120, 360, 850, 1025, 1200];

    protected int $building_cost = 150;
}