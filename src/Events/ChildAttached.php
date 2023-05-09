<?php

namespace Thunk\Verbs\Events;

use Thunk\Verbs\Event;
use Thunk\Verbs\Snowflakes\Snowflake;

class ChildAttached extends Event
{
    public function __construct(public Snowflake $child_id)
    {
    }
}
