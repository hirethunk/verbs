<?php

namespace Thunk\Verbs\Events;

use Illuminate\Support\Facades\DB;
use Thunk\Verbs\Facades\Snowflake;

abstract class Event
{
    public static function fire(...$args): void
    {
		$event = new static(...$args);
		
        app(Dispatcher::class)->fire($event);
		app(Store::class)->insert($event);
    }
}
