<?php

namespace Thunk\Verbs\Contracts;

use Illuminate\Support\LazyCollection;
use Thunk\Verbs\Context;
use Thunk\Verbs\Event;
use Thunk\Verbs\Support\Snowflake;

interface ContextRepository
{
    public function apply(Event $event): void;

    public function get(string $class_name, string $id): Context;
}
