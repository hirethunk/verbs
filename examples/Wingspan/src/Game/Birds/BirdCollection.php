<?php

namespace Thunk\Verbs\Examples\Wingspan\Game\Birds;

use Illuminate\Support\Collection;
use Illuminate\Support\Enumerable;

class BirdCollection extends Collection
{
    public function contains($key, $operator = null, $value = null)
    {
        if ($key instanceof Bird) {
            return $this->contains(fn ($value) => $key->is($value));
        }

        return parent::contains($key, $operator, $value);
    }

    /** @param \Illuminate\Support\Enumerable<array-key, Bird>|array<array-key, Bird>|Bird|string $keys */
    public function except($keys)
    {
        if ($keys instanceof Bird) {
            foreach ($this->items as $key => $value) {
                if ($keys->is($value)) {
                    $keys = $key;
                    break;
                }
            }
        }

        return parent::except($keys);
    }

    public function is(Enumerable|array $birds): bool
    {
        $expected = $this
            ->map(fn (Bird $bird) => $bird::class)
            ->sort()
            ->all();

        $comparison = collect($birds)
            ->ensure(Bird::class)
            ->map(fn (Bird $bird) => $bird::class)
            ->sort()
            ->all();

        return $expected === $comparison;
    }
}
