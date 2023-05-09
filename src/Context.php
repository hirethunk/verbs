<?php

namespace Thunk\Verbs;

use Thunk\Verbs\Facades;
use Thunk\Verbs\Snowflakes\Snowflake;

abstract class Context
{
    public ?Snowflake $last_event_id = null;

    public static function load(int|string|Snowflake $id): static
    {
        $context = new static(Facades\Snowflake::coerce($id));

        return Facades\Contexts::sync($context);
    }

    public function __construct(public Snowflake $id)
    {
    }
}
