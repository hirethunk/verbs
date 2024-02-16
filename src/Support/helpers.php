<?php

namespace {

    use Thunk\Verbs\Event;
    use Thunk\Verbs\Support\PendingEvent;

    if (! function_exists('verb')) {
        /**
         * @template TEventType
         *
         * @param  Event<TEventType>  $event
         * @return Event<TEventType>
         */
        function verb(Event $event, bool $commit = false): Event
        {
            $pending = new PendingEvent($event);

            return $commit ? $pending->commit() : $pending->fire();
        }
    }
}

namespace Thunk\Verbs {

    use Thunk\Verbs\Support\IdManager;

    function make_id()
    {
        return app(IdManager::class)->make();
    }
}
