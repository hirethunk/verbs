<?php

namespace Thunk\Verbs\Examples\Monopoly\Game\{Spaces\Properties};

use Thunk\Verbs\Examples\Monopoly\Game\PropertyColor;
use Thunk\Verbs\Examples\Monopoly\Game\Spaces\Property;

class MarvinGardens extends Property
{
    protected string $name = 'Marvin Gardens';

    protected PropertyColor $color = PropertyColor::Yellow;

    protected int $position = 29;

    protected int $price = 280;

    /** @var int[] */
    protected array $rent = [24, 120, 360, 850, 1025, 1200];

    protected int $building_cost = 150;
}