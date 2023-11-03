<?php

namespace Thunk\Verbs\Examples\Monopoly\Game\Spaces\Details;

use Thunk\Verbs\Examples\Monopoly\Game\PropertyColor;
use Thunk\Verbs\Examples\Monopoly\Game\Spaces\PropertyDetails;

class VirginiaAvenue extends PropertyDetails
{
    protected string $name = 'Virginia Avenue';

    protected PropertyColor $color = PropertyColor::Pink;

    protected int $position = 14;

    protected int $price = 160;

    /** @var int[] */
    protected array $rent = [12, 60, 180, 500, 700, 900];

    protected int $building_cost = 100;
}