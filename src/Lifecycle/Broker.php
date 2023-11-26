<?php

namespace Thunk\Verbs\Lifecycle;

use Glhd\Bits\Bits;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Uid\AbstractUid;
use Thunk\Verbs\Event;
use Thunk\Verbs\Lifecycle\Queue as EventQueue;
use Thunk\Verbs\Models\VerbEvent;
use WeakMap;

class Broker
{
    public bool $is_replaying = false;

    public function __construct(
        protected Dispatcher $dispatcher,
    ) {
    }

    public function fire(Event $event): Event
    {
        // NOTE: Any changes to how the dispatcher is called here
        // should also be applied to the `replay` method

        $states = $event->states();

        $states->each(fn ($state) => Guards::for($event, $state)->check());

        $event->phase = Phase::Apply;
        $states->each(fn ($state) => $this->dispatcher->apply($event, $state));

        $event->phase = Phase::Fired;
        $this->dispatcher->fired($event, $states);

        app(Queue::class)->queue($event);

        return $event;
    }

    public function commit(WeakMap $results = null): WeakMap
    {
        $results ??= new WeakMap();

        $events = app(EventQueue::class)->flush();

        // FIXME: Only write changes + handle aggregate versioning

        app(StateManager::class)->writeSnapshots();

        if (empty($events)) {
            return $results;
        }

        foreach ($events as $event) {
            $event->phase = Phase::Handle;
            $results[$event] = $this->dispatcher->handle($event);
        }

        return $this->commit($results);
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

    public function isReplaying(): bool
    {
        return $this->is_replaying;
    }

    public function unlessReplaying(callable $callback)
    {
        if (! $this->is_replaying) {
            $callback();
        }
    }

    public function toId(Bits|UuidInterface|AbstractUid|int|string|null $id): int|string|null
    {
        return match (true) {
            $id instanceof Bits => $id->id(),
            $id instanceof UuidInterface => $id->toString(),
            $id instanceof AbstractUid => (string) $id,
            default => $id,
        };
    }
}
