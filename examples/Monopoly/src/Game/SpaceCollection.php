<?php

namespace Thunk\Verbs\Examples\Monopoly\Game;

use Illuminate\Support\Collection;
use Thunk\Verbs\Examples\Monopoly\Game\Spaces\Space;
use Thunk\Verbs\SerializedByVerbs;

class SpaceCollection extends Collection implements SerializedByVerbs
{
	public static function deserializeForVerbs(mixed $data): static
	{
		return static::make($data)
			->map(fn($serialized) => Space::deserializeForVerbs($serialized));
	}
	
	public function serializeForVerbs(): string|array
	{
		return $this->map(fn(Space $space) => $space->serializeForVerbs())->toJson();
	}
}
