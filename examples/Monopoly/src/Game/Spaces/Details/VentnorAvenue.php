<?php

namespace Thunk\Verbs\Examples\Monopoly\Game\Spaces\Details;

use Thunk\Verbs\Examples\Monopoly\Game\PropertyColor;
use Thunk\Verbs\Examples\Monopoly\Game\Spaces\PropertyDetails;

class VentnorAvenue extends PropertyDetails
{
    public string $name = 'Ventnor Avenue';

    public PropertyColor $color = PropertyColor::Yellow;

    public int $position = 27;

    public int $price = 260;

    /** @var int[] */
    public array $rent = [22, 110, 330, 800, 975, 1150];

    public int $building_cost = 150;
}
