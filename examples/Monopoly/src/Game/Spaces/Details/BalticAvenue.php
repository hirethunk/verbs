<?php

namespace Thunk\Verbs\Examples\Monopoly\Game\Spaces\Details;

use Thunk\Verbs\Examples\Monopoly\Game\PropertyColor;
use Thunk\Verbs\Examples\Monopoly\Game\Spaces\PropertyDetails;

class BalticAvenue extends PropertyDetails
{
    protected string $name = 'Baltic Avenue';

    protected PropertyColor $color = PropertyColor::Brown;

    protected int $position = 3;

    protected int $price = 60;

    /** @var int[] */
    protected array $rent = [4, 20, 60, 180, 320, 450];

    protected int $building_cost = 50;
}