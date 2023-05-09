<?php

namespace Thunk\Verbs;

use Thunk\Verbs\Snowflakes\Snowflake;

abstract class Context
{
    public ?Snowflake $last_event_id = null;
    
    public function __construct(public Snowflake $id)
    {
    }
}
