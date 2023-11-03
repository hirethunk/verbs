<?php

namespace Thunk\Verbs\Examples\Wingspan\Game;

use Illuminate\Support\Collection;
use Illuminate\Support\Enumerable;
use InvalidArgumentException;
use Throwable;

class FoodCollection extends Collection
{
    public function containsAll(array|Enumerable $food): bool
    {
        try {
            $this->consume($food);

            return true;
        } catch (Throwable) {
        }

        return false;
    }

    public function consume(array|Enumerable $food): static
    {
        $items = $this->items;

        foreach ($food as $to_remove) {
            if (! $key = array_search($to_remove, $this->items)) {
                throw new InvalidArgumentException('Cannot use food that you do not have.');
            }

            unset($items[$key]);
        }

        return static::make($items);
    }

    public function is(Enumerable|array $foods): bool
    {
        $expected = $this
            ->map(fn (Food $food) => $food->value)
            ->sort()
            ->all();

        $comparison = collect($foods)
            ->ensure(Food::class)
            ->map(fn (Food $food) => $food->value)
            ->sort()
            ->all();

        return $expected === $comparison;
    }
}
