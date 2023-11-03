<?php

namespace Thunk\Verbs\Examples\Monopoly\Game\Spaces\Details;

use Thunk\Verbs\Examples\Monopoly\Game\PropertyColor;
use Thunk\Verbs\Examples\Monopoly\Game\Spaces\PropertyDetails;

class BalticAvenue extends PropertyDetails
{
    public string $name = 'Baltic Avenue';

    public PropertyColor $color = PropertyColor::Brown;

    public int $position = 3;

    public int $price = 60;

    /** @var int[] */
    public array $rent = [4, 20, 60, 180, 320, 450];

    public int $building_cost = 50;
}
