<?php

namespace Thunk\Verbs\Examples\Monopoly\Game\Spaces\Details;

use Thunk\Verbs\Examples\Monopoly\Game\PropertyColor;
use Thunk\Verbs\Examples\Monopoly\Game\Spaces\PropertyDetails;

class ConnecticutAvenue extends PropertyDetails
{
    protected string $name = 'Connecticut Avenue';

    protected PropertyColor $color = PropertyColor::LightBlue;

    protected int $position = 9;

    protected int $price = 120;

    /** @var int[] */
    protected array $rent = [8, 40, 100, 300, 450, 600];

    protected int $building_cost = 50;
}