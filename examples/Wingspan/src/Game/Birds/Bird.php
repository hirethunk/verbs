<?php

namespace Thunk\Verbs\Examples\Wingspan\Game\Birds;

use Illuminate\Support\Collection;
use Thunk\Verbs\Examples\Wingspan\Game\Food;

abstract class Bird
{
    public int $points;

    /** @var Food[] */
    public array $cost;

    public function is($bird): bool
    {
        return $bird instanceof Bird && $bird::class === $this::class;
    }

    public function name(): string
    {
        return str(static::class)->classBasename()->headline();
    }

    public function eats(Collection $food): bool
    {
        foreach ($this->cost as $cost) {
            if (! $food->contains($cost)) {
                return false;
            }
        }

        return true;
    }

    public function points(): int
    {
        return $this->points;
    }
}
