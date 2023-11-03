<?php

namespace Thunk\Verbs\Examples\Monopoly\Game\Spaces\Details;

use Thunk\Verbs\Examples\Monopoly\Game\PropertyColor;
use Thunk\Verbs\Examples\Monopoly\Game\Spaces\PropertyDetails;

class VermontAvenue extends PropertyDetails
{
    public string $name = 'Vermont Avenue';

    public PropertyColor $color = PropertyColor::LightBlue;

    public int $position = 8;

    public int $price = 100;

    /** @var int[] */
    public array $rent = [6, 30, 90, 270, 400, 550];

    public int $building_cost = 50;
}
