<?php

namespace Thunk\Verbs;

use Glhd\Bits\Snowflake;

abstract class Event
{
    public static function fire(...$args): static
    {
        return new static(...$args);
    }
}
