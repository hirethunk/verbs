<?php

use Thunk\Verbs\Event;
use Thunk\Verbs\Support\PendingEvent;

if (! function_exists('verb')) {
    function verb(Event $event): Event
    {
        return (new PendingEvent($event))->fire();
    }
}
