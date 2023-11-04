<?php

namespace Thunk\Verbs\Examples\Monopoly\Game\Spaces;

abstract class Space
{
    protected string $name;

    protected int $position;

    protected static array $instances = [];

    public static function instance(): static
    {
        return static::$instances[static::class] ??= new static();
    }

    public function name(): string
    {
        return $this->name;
    }

    public function position(): int
    {
        return $this->position;
    }
}
