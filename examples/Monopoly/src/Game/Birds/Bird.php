<?php

namespace Thunk\Verbs\Examples\Monopoly\Game\Birds;

use Glhd\Bits\Snowflake;
use Illuminate\Support\Collection;
use Thunk\Verbs\Examples\Monopoly\Game\Food;
use Thunk\Verbs\Examples\Monopoly\Game\Habitat;
use Thunk\Verbs\SerializedByVerbs;
use UnexpectedValueException;

abstract class Bird implements SerializedByVerbs
{
    public int $id;

    public int $points;

    /** @var Food[] */
    public array $cost;

    public Habitat $habitat;

    public int $egg_count = 0;

    public function __construct()
    {
        $this->id = Snowflake::make()->id();
    }

    public static function deserializeForVerbs(mixed $data): static
    {
        if ($data instanceof static) {
            return $data;
        }

        if (
            is_array($data)
            && isset($data['fqcn'], $data['id'])
            && is_a($data['fqcn'], Bird::class)
        ) {
            $bird = new $data['fqcn'];
            $bird->id = $data['id'];
            $bird->egg_count = $data['egg_count'] ?? 0;

            return $bird;
        }

        throw new UnexpectedValueException;
    }

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

    public function livesIn(Habitat $habitat): bool
    {
        return $this->habitat === $habitat;
    }

    public function points(): int
    {
        return $this->points;
    }

    public function serializeForVerbs(): string|array
    {
        return [
            'fqcn' => static::class,
            'id' => $this->id,
            'egg_count' => $this->egg_count,
        ];
    }
}
