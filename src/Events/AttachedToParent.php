<?php

namespace Thunk\Verbs\Events;

use Thunk\Verbs\Event;
use Thunk\Verbs\Snowflakes\Snowflake;

class AttachedToParent extends Event
{
    public function __construct(public Snowflake $parent_id)
    {
    }
}
