<?php

use Thunk\Verbs\Contracts\BrokersEvents;
use Thunk\Verbs\Event;
use Thunk\Verbs\Support\PendingEvent;

if (! function_exists('verb')) {
    /**
     * @template TEventType of Event
     *
     * @param  TEventType  $event
     * @return TEventType
     */
    function verb(Event $event, bool $commit = false): Event
    {
        (new PendingEvent($event))->fire();

        if ($commit) {
            app(BrokersEvents::class)->commit();
        }

        return $event;
    }
}
