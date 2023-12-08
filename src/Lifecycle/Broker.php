<?php

namespace Thunk\Verbs\Lifecycle;

use Glhd\Bits\Bits;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Uid\AbstractUid;
use Thunk\Verbs\Event;
use Thunk\Verbs\Lifecycle\Queue as EventQueue;

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

    public function commit(): bool
    {
        $events = app(EventQueue::class)->flush();

        // FIXME: Only write changes + handle aggregate versioning

        app(StateManager::class)->writeSnapshots();

        if (empty($events)) {
            return true;
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
            ->each(function (Event $event) {
                app(StateManager::class)->setMaxEventId($event->id);

                $event->states()
                    ->each(fn ($state) => $this->dispatcher->apply($event, $state))
                    ->each(fn ($state) => $this->dispatcher->replay($event, $state))
                    ->whenEmpty(fn () => $this->dispatcher->replay($event, null));

                return $event;
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

    public function createMetadataUsing(?callable $callback = null): void
    {
        app(MetadataManager::class)->createMetadataUsing($callback);
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
