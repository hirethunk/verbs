<?php

namespace Thunk\Verbs;

use Thunk\Verbs\Lifecycle\Broker;

abstract class Event
{
    public static function fire(...$args): void
    {
        app(Broker::class)->fire(new static(...$args));
    }
}
