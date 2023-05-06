<?php

namespace Thunk\Verbs\Events;

abstract class Event
{
    public static function fire(...$args): void
    {
        $event = new static(...$args);
	    
	    Lifecycle::for($event)->authorize()->validate();

        app(Dispatcher::class)->fire($event);
        app(Store::class)->insert($event);
    }
}
