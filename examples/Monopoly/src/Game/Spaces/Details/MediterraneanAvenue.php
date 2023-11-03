<?php

namespace Thunk\Verbs\Examples\Monopoly\Game\Spaces\Details;

use Thunk\Verbs\Examples\Monopoly\Game\PropertyColor;
use Thunk\Verbs\Examples\Monopoly\Game\Spaces\PropertyDetails;

class MediterraneanAvenue extends PropertyDetails
{
    public string $name = 'Mediterranean Avenue';

    public PropertyColor $color = PropertyColor::Brown;

    public int $position = 1;

    public int $price = 60;

    /** @var int[] */
    public array $rent = [2, 10, 30, 90, 160, 250];

    public int $building_cost = 50;
}
