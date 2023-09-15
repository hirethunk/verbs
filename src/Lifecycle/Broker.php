<?php

namespace Thunk\Verbs\Lifecycle;

use Thunk\Verbs\Event;
use Thunk\Verbs\Lifecycle\Queue as EventQueue;
use Thunk\Verbs\Support\Reflector;
use Thunk\Verbs\VerbSnapshot;

class Broker
{
    public function commit()
    {
        $events = app(EventQueue::class)->flush();

        // FIXME: Only write changes + handle aggregate versioning
        app(StateStore::class)->writeLoaded();

        if (empty($events)) {
            return;
        }

        foreach ($events as $event) {
            app(Dispatcher::class)->fire($event);
        }

        return $this->commit();
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
        return Reflector::getPublicStateProperties($event);
    }
}
