<?php

namespace Thunk\Verbs\Events;

abstract class Event
{
    public static function fire(...$args): void
    {
        app(Broker::class)->fire(new static(...$args));
    }
}
