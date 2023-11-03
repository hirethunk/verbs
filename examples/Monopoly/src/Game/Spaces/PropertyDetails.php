<?php

namespace Thunk\Verbs\Examples\Monopoly\Game\Spaces;

use Brick\Money\Money;
use Thunk\Verbs\Examples\Monopoly\Game\PropertyColor;

abstract class PropertyDetails extends SpaceDetails
{
    protected PropertyColor $color;

    protected int $price;

    /** @var int[] */
    protected array $rent;

    protected int $building_cost;

    protected int $development = 0;

    protected bool $is_mortgaged = false;

    public function color(): PropertyColor
    {
        return $this->color;
    }

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

    public function isMortgaged(): bool
    {
        return $this->is_mortgaged;
    }
}
