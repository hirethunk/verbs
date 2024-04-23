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
        $event = (new PendingEvent($event))->fire();

        if ($commit) {
            app(BrokersEvents::class)->commit();
        }

        return $event;
    }
}

if (! function_exists('ensure_type')) {
    function ensure_type(string $class, string $interface): string
    {
        if (! is_a($class, $interface, true)) {
            throw new InvalidArgumentException("Class [{$class}] must implement [{$interface}]");
        }

        return $class;
    }
}
