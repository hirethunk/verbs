<?php

namespace Thunk\Verbs\Contracts;

use Thunk\Verbs\Context;
use Thunk\Verbs\Event;

interface ContextRepository
{
    public function apply(Event $event): void;

    public function get(string $class_name, string $id): Context;
}
