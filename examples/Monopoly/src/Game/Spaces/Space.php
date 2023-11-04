<?php

namespace Thunk\Verbs\Examples\Monopoly\Game\Spaces;

use BadMethodCallException;

abstract class Space
{
    protected string $name;

    protected int $position;

    protected static array $instances = [];

    public static function instance(): static
    {
        return self::$instances[static::class] ?? new static();
    }

    public function __construct()
    {
        if (isset(self::$instances[static::class])) {
            throw new BadMethodCallException('An instance of '.class_basename($this).' already exists.');
        }

        self::$instances[static::class] = $this;
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
