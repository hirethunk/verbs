<?php

namespace Thunk\Verbs;

abstract class Event
{
    public static function fire(...$args): static
    {
        return new static(...$args);
    }
}
