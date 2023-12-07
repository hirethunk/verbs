<?php

namespace Thunk\Verbs\Lifecycle;

use Glhd\Bits\Bits;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Uid\AbstractUid;
use Thunk\Verbs\Event;
use Thunk\Verbs\Lifecycle\Queue as EventQueue;
use Thunk\Verbs\Models\VerbEvent;

class Broker
{
    public bool $is_replaying = false;

    public function __construct(
        protected Dispatcher $dispatcher,
        protected MetadataManager $metadata,
    ) {
    }

    public function fire(Event $event): Event
    {
        // NOTE: Any changes to how the dispatcher is called here
        // should also be applied to the `replay` method

        $states = $event->states();

        $states->each(fn ($state) => Guards::for($event, $state)->check());

        $states->each(fn ($state) => $this->dispatcher->apply($event, $state));

        $this->dispatcher->fired($event, $states);

        app(Queue::class)->queue($event);

        return $event;
    }

    public function commit()
    {
        $events = app(EventQueue::class)->flush();

        app(StateManager::class)->writeSnapshots();

        if (empty($events)) {
            return null;
        }

        foreach ($events as $event) {
            $this->metadata->setLastResults($event, $this->dispatcher->handle($event));
        }

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
                    ->each(fn ($state) => $this->dispatcher->replay($model->event(), $state))
                    ->whenEmpty(fn () => $this->dispatcher->replay($model->event(), null));

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
