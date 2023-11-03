<?php

namespace Thunk\Verbs\Examples\Monopoly\Game\Spaces\Details;

use Thunk\Verbs\Examples\Monopoly\Game\PropertyColor;
use Thunk\Verbs\Examples\Monopoly\Game\Spaces\PropertyDetails;

class PennsylvaniaAvenue extends PropertyDetails
{
    protected string $name = 'Pennsylvania Avenue';

    protected PropertyColor $color = PropertyColor::Green;

    protected int $position = 34;

    protected int $price = 320;

    /** @var int[] */
    protected array $rent = [28, 150, 450, 1000, 1200, 1400];

    protected int $building_cost = 200;
}