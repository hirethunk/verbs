<?php

namespace Thunk\Verbs\Lifecycle;

use Glhd\Bits\Bits;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Uid\AbstractUid;
use Throwable;
use Thunk\Verbs\Event;
use Thunk\Verbs\Exceptions\EventNotValidForCurrentState;

trait BrokerConvenienceMethods
{
    public bool $is_replaying = false;

    public function createMetadataUsing(?callable $callback = null): void
    {
        app(MetadataManager::class)->createMetadataUsing($callback);
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

    public function isReplaying(): bool
    {
        return $this->is_replaying;
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

    public function toId(Bits|UuidInterface|AbstractUid|int|string|null $id): int|string|null
    {
        return match (true) {
            $id instanceof Bits => $id->id(),
            $id instanceof UuidInterface => $id->toString(),
            $id instanceof AbstractUid => (string) $id,
            default => $id,
        };
    }

    public function unlessReplaying(callable $callback)
    {
        if (! $this->is_replaying) {
            $callback();
        }
    }
}
