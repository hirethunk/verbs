<?php

namespace Thunk\Verbs;

use Thunk\Verbs\Lifecycle\Broker;

abstract class Event
{
    public static function fire(...$args): static
    {
        $event = new static(...$args);

        return app(Broker::class)->fire($event);
    }
}
