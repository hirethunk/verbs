<?php

namespace Thunk\Verbs\Lifecycle;

use Carbon\CarbonInterface;
use Glhd\Bits\Bits;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Uid\AbstractUid;
use Throwable;
use Thunk\Verbs\Event;
use Thunk\Verbs\Exceptions\EventNotValidForCurrentState;
use Thunk\Verbs\Facades\Id;
use Thunk\Verbs\Support\IdManager;
use Thunk\Verbs\Support\Wormhole;

trait BrokerConvenienceMethods
{
    public bool $is_replaying = false;

    /**
     * @deprecated
     * @see IdManager
     * @see Id
     */
    public function toId(Bits|UuidInterface|AbstractUid|int|string|null $id): int|string|null
    {
        return match (true) {
            $id instanceof Bits => $id->id(),
            $id instanceof UuidInterface => $id->toString(),
            $id instanceof AbstractUid => (string) $id,
            default => $id,
        };
    }

    public function createMetadataUsing(?callable $callback = null): void
    {
        app(MetadataManager::class)->createMetadataUsing($callback);
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

    public function unlessReplaying(callable $callback)
    {
        if (! $this->is_replaying) {
            $callback();
        }
    }

    public function realNow(): CarbonInterface
    {
        return app(Wormhole::class)->realNow();
    }
}
