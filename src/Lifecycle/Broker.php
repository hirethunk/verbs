<?php

namespace Thunk\Verbs\Lifecycle;

use Thunk\Verbs\Event;
use Thunk\Verbs\Lifecycle\Queue as EventQueue;
use Thunk\Verbs\Models\VerbEvent;

class Broker
{
    public bool $is_replaying = false;

    public function __construct(
        protected Dispatcher $dispatcher,
    ) {
    }

    public function fire(Event $event): Event
    {
        $states = $event->states();

        $states->each(fn ($state) => Guards::for($event, $state)->check());

        $event->phase = Phase::Apply;
        $states->each(fn ($state) => $this->dispatcher->apply($event, $state));

        $this->dispatcher->fired($event, $states);

        app(Queue::class)->queue($event);

        $event->phase = Phase::Fired;

        return $event;
    }

    public function commit(): bool
    {
        $events = app(EventQueue::class)->flush();

        // FIXME: Only write changes + handle aggregate versioning

        app(StateManager::class)->writeSnapshots();

        if (empty($events)) {
            return true;
        }

        foreach ($events as $event) {
            $this->dispatcher->handle($event);
        }

        $event->phase = Phase::Handle;

        return $this->commit();
    }

    public function replay()
    {
        $this->is_replaying = true;

        app(SnapshotStore::class)->reset();

        app(EventStore::class)->read()
            ->each(function (VerbEvent $model) {
                app(StateManager::class)->setMaxEventId($model->id);

                $model->event()->states()
                    ->each(fn ($state) => $this->dispatcher->apply($model->event(), $state))
                    ->each(fn ($state) => $this->dispatcher->replay($model->event(), $state));

                return $model->event();
            });

        $this->is_replaying = false;
    }

    public function unlessReplaying(callable $callback)
    {
        if (! $this->is_replaying) {
            $callback();
        }
    }
}
