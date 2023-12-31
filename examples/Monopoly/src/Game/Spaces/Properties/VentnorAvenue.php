<?php

namespace Thunk\Verbs\Examples\Monopoly\Game\Spaces\Properties;

use Thunk\Verbs\Examples\Monopoly\Game\PropertyColor;
use Thunk\Verbs\Examples\Monopoly\Game\Spaces\Property;

class VentnorAvenue extends Property
{
    protected string $name = 'Ventnor Avenue';

    protected PropertyColor $color = PropertyColor::Yellow;

    protected int $position = 27;

    protected int $price = 260;

    /** @var int[] */
    protected array $rent = [22, 110, 330, 800, 975, 1150];

    protected int $building_cost = 150;
}
