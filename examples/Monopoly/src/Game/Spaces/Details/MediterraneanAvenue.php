<?php

namespace Thunk\Verbs\Examples\Monopoly\Game\Spaces\Details;

use Thunk\Verbs\Examples\Monopoly\Game\PropertyColor;
use Thunk\Verbs\Examples\Monopoly\Game\Spaces\PropertyDetails;

class MediterraneanAvenue extends PropertyDetails
{
    protected string $name = 'Mediterranean Avenue';

    protected PropertyColor $color = PropertyColor::Brown;

    protected int $position = 1;

    protected int $price = 60;

    /** @var int[] */
    protected array $rent = [2, 10, 30, 90, 160, 250];

    protected int $building_cost = 50;
}