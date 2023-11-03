<?php

namespace Thunk\Verbs\Examples\Monopoly\Game\Spaces\Details;

use Thunk\Verbs\Examples\Monopoly\Game\PropertyColor;
use Thunk\Verbs\Examples\Monopoly\Game\Spaces\PropertyDetails;

class StatesAvenue extends PropertyDetails
{
    protected string $name = 'States Avenue';

    protected PropertyColor $color = PropertyColor::Pink;

    protected int $position = 13;

    protected int $price = 140;

    /** @var int[] */
    protected array $rent = [10, 50, 150, 450, 625, 750];

    protected int $building_cost = 100;
}