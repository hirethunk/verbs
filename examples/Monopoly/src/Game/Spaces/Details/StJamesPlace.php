<?php

namespace Thunk\Verbs\Examples\Monopoly\Game\Spaces\Details;

use Thunk\Verbs\Examples\Monopoly\Game\PropertyColor;
use Thunk\Verbs\Examples\Monopoly\Game\Spaces\PropertyDetails;

class StJamesPlace extends PropertyDetails
{
    protected string $name = 'St. James Place';

    protected PropertyColor $color = PropertyColor::Orange;

    protected int $position = 16;

    protected int $price = 180;

    /** @var int[] */
    protected array $rent = [14, 70, 200, 550, 750, 950];

    protected int $building_cost = 100;
}