<?php

namespace Thunk\Verbs\Lifecycle;

use Glhd\Bits\Bits;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Uid\AbstractUid;
use Throwable;
use Thunk\Verbs\CommitsImmediately;
use Thunk\Verbs\Event;
use Thunk\Verbs\Exceptions\EventNotValidForCurrentState;
use Thunk\Verbs\Lifecycle\Queue as EventQueue;

class Broker
{
    public bool $is_replaying = false;

    public bool $commit_immediately = false;

    public function __construct(
        protected Dispatcher $dispatcher,
        protected MetadataManager $metadata,
    ) {
    }

    public function fire(Event $event): ?Event
    {
        if ($this->is_replaying) {
            return null;
        }

        // NOTE: Any changes to how the dispatcher is called here
        // should also be applied to the `replay` method

        $states = $event->states();

        $states->each(fn ($state) => Guards::for($event, $state)->check());

        Guards::for($event, null)->check();

        $states->each(fn ($state) => $this->dispatcher->apply($event, $state));

        $this->dispatcher->fired($event, $states);

        app(Queue::class)->queue($event);

        if ($this->commit_immediately || $event instanceof CommitsImmediately) {
            $this->commit();
        }

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

    public function isValid(Event $event): bool
    {
        try {
            $states = $event->states();

            Guards::for($event, null)->validate();
            $states->each(fn ($state) => Guards::for($event, $state)->validate());

            return true;
        } catch (EventNotValidForCurrentState $e) {
            return false;
        }
    }

    public function isAllowed(Event $event): bool
    {
        try {
            $states = $event->states();

            Guards::for($event, null)->authorize();
            $states->each(fn ($state) => Guards::for($event, $state)->authorize());

            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    public function replay(?callable $beforeEach = null, ?callable $afterEach = null)
    {
        $this->is_replaying = true;

        app(SnapshotStore::class)->reset();

        app(EventStore::class)->read()
            ->each(function (Event $event) use ($beforeEach, $afterEach) {
                app(StateManager::class)->setMaxEventId($event->id);

                if ($beforeEach) {
                    $beforeEach($event);
                }

                $created_at = app(MetadataManager::class)->getEphemeral($event, 'created_at', now());

                $event->states()
                    ->each(fn ($state) => $this->dispatcher->apply($event, $state))
                    ->each(fn ($state) => $this->dispatcher->replay($event, $state, $created_at))
                    ->whenEmpty(fn () => $this->dispatcher->replay($event, null, $created_at));

                if ($afterEach) {
                    $afterEach($event);
                }

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

    public function commitImmediately(bool $commit_immediately = true): void
    {
        $this->commit_immediately = $commit_immediately;
    }
}
