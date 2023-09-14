<?php

namespace Thunk\Verbs\Lifecycle;

use Thunk\Verbs\Event;
use Thunk\Verbs\Lifecycle\Queue as EventQueue;
use Thunk\Verbs\Support\Reflector;

class Broker
{
    public function fireQueuedEvents()
    {
        $events = app(EventQueue::class)->flush();

        if (empty($events)) {
            return;
        }

        foreach ($events as $event) {
            app(Dispatcher::class)->fire($event);
        }

        return $this->fireQueuedEvents();
    }

    public function fire(Event $event)
    {
        $states = $this->enumerateStates($event);

        $states->each(fn ($state) => Guards::for($event, $state)->check());
        $states->each(fn ($state) => app(Dispatcher::class)->apply($event, $state));

        app(Queue::class)->queue($event);

        return $event;
    }

    public function enumerateStates(Event $event)
    {
        return Reflector::getPublicStateProperties($event)
            ->map(fn ($_, $property_name) => $event->{$property_name});
    }
}
