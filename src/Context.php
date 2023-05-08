<?php

namespace Thunk\Verbs;

use Thunk\Verbs\Support\Snowflake;

abstract class Context
{
    public ?Snowflake $last_event_id = null;
}
