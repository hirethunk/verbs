<?php

namespace Thunk\Verbs\Contracts;

use Thunk\Verbs\Context;
use Thunk\Verbs\Event;
use Thunk\Verbs\Snowflakes\Snowflake;

interface ContextRepository
{
    public function apply(Event $event): void;

    public function get(string $class_name, Snowflake $id): Context;
}
