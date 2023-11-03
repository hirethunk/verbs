<?php

namespace Thunk\Verbs\Examples\Monopoly\Game\Spaces\Details;

use Thunk\Verbs\Examples\Monopoly\Game\PropertyColor;
use Thunk\Verbs\Examples\Monopoly\Game\Spaces\PropertyDetails;

class IndianaAvenue extends PropertyDetails
{
    protected string $name = 'Indiana Avenue';

    protected PropertyColor $color = PropertyColor::Red;

    protected int $position = 23;

    protected int $price = 220;

    /** @var int[] */
    protected array $rent = [18, 90, 250, 700, 875, 1050];

    protected int $building_cost = 150;
}