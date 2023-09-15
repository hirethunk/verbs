<?php

namespace Thunk\Verbs\Lifecycle;

use Thunk\Verbs\Event;
use Thunk\Verbs\Lifecycle\Queue as EventQueue;
use Thunk\Verbs\Models\VerbEvent;
use Thunk\Verbs\Support\Reflector;

class Broker
{
    public function fire(Event $event)
    {
        $states = collect($event->states());

        $states->each(fn ($state) => Guards::for($event, $state)->check());
        $states->each(fn ($state) => app(Dispatcher::class)->apply($event, $state));

        app(Queue::class)->queue($event);

        $event->fired = true;

        return $event;
    }

    public function commit(): bool
    {
        $events = app(EventQueue::class)->flush();

        // FIXME: Only write changes + handle aggregate versioning
        app(StateStore::class)->writeLoaded();

        if (empty($events)) {
            return true;
        }

        foreach ($events as $event) {
            app(Dispatcher::class)->fire($event);
        }

        return $this->commit();
    }

    public function replay()
    {
        app(StateStore::class)->reset();

        app(EventStore::class)->read()
            ->each(function (VerbEvent $model) {
                $event = $model->type::hydrate($model->id, $model->data);
                $event->fired = true;

                $states = Reflector::getPublicStateProperties($event);
                $states->each(fn ($state) => app(Dispatcher::class)->apply($event, $state));
            });

        app(Queue::class)->queue($event);

        return $event;
    }
}
