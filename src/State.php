<?php

namespace Thunk\Verbs;

use Glhd\Bits\Snowflake;

abstract class State
{
    protected int|string|null $id;

    public static function initialize(): static
    {
        return new static();
    }

    public static function load($from): static
    {
        $key = is_object($from) && method_exists($from, 'getVerbsStateKey')
            ? $from->getVerbsStateKey()
            : $from;

        static::loadByKey($key);
    }

    public static function loadByKey($from): static
    {
        // FIXME
    }

    public static function singleton(): static
    {
        // FIXME
    }

    public function id(): int|string
    {
        return $this->id ??= Snowflake::make()->id();
    }
}
