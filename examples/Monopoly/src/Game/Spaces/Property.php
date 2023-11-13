<?php

namespace Thunk\Verbs\Examples\Monopoly\Game\Spaces;

use Brick\Money\Money;
use Thunk\Verbs\Examples\Monopoly\Game\PropertyColor;
use Thunk\Verbs\Examples\Monopoly\States\PlayerState;

abstract class Property extends Space
{
    protected PropertyColor $color;

    protected int $price;

    /** @var int[] */
    protected array $rent;

    protected int $building_cost;

    protected int $development = 0;

    protected bool $is_mortgaged = false;

    protected ?int $owner_id = null;
	
	public static function deserializeForVerbs(mixed $data): static
	{
		$space = parent::deserializeForVerbs($data);
		
		$space->color = data_get($data, 'color');
		$space->price = data_get($data, 'price');
		$space->rent = data_get($data, 'rent');
		$space->building_cost = data_get($data, 'building_cost');
		$space->development = data_get($data, 'development');
		$space->is_mortgaged = data_get($data, 'is_mortgaged');
		$space->owner_id = data_get($data, 'owner_id');
		
		return $space;
	}
	
	public function serializeForVerbs(): string|array
	{
		return array_merge(parent::serializeForVerbs(), [
			'color' => $this->color->value,
			'price' => $this->price,
			'rent' => $this->rent,
			'building_cost' => $this->building_cost,
			'development' => $this->development,
			'is_mortgaged' => $this->is_mortgaged,
			'owner_id' => $this->owner_id,
		]);
	}

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

    public function isOwned(): bool
    {
        return $this->owner_id !== null;
    }

    public function owner(): ?PlayerState
    {
        return $this->owner_id ? PlayerState::load($this->owner_id) : null;
    }

    public function setOwner(int|PlayerState $player)
    {
        $this->owner_id = $player instanceof PlayerState ? $player->id : $player;
    }
}
