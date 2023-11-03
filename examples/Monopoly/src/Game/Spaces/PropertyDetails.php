<?php

namespace Thunk\Verbs\Examples\Monopoly\Game\Spaces;

use Brick\Money\Money;
use Thunk\Verbs\Examples\Monopoly\Game\PropertyColor;

abstract class PropertyDetails extends SpaceDetails
{
    public PropertyColor $color;

    public int $price;

    /** @var int[] */
    public array $rent;

    public int $building_cost;

    public int $development = 0;

    public bool $mortgaged = false;

    public function price(): Money
    {
        return Money::of($this->price, 'USD');
    }

    public function rent(): Money
    {
        return Money::of($this->rent[$this->development], 'USD');
    }

    public function buildingCost(): Money
    {
        return Money::of($this->building_cost, 'USD');
    }
}
