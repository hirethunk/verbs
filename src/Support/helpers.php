<?php

use Thunk\Verbs\Event;
use Thunk\Verbs\Support\PendingEvent;

if (! function_exists('verb')) {
    function verb(Event $event, bool $commit = false): Event
    {
        $pending = new PendingEvent($event);

        return $commit ? $pending->commit() : $pending->fire();
    }
}
