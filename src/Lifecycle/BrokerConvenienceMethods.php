<?php

namespace Thunk\Verbs\Lifecycle;

use Carbon\CarbonInterface;
use Glhd\Bits\Bits;
use Illuminate\Auth\Access\AuthorizationException;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Uid\AbstractUid;
use Thunk\Verbs\Event;
use Thunk\Verbs\Exceptions\EventNotAuthorized;
use Thunk\Verbs\Exceptions\EventNotValid;
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

    public function isAuthorized(Event $event): bool
    {
        try {
            Guards::for($event)->authorize();
            $event->states()->each(fn ($state) => Guards::for($event, $state)->authorize());

            return true;
        } catch (AuthorizationException) {
            return false;
        }
    }

    public function isValid(Event $event): bool
    {
        try {
            Guards::for($event)->validate();
            $event->states()->each(fn ($state) => Guards::for($event, $state)->validate());

            return true;
        } catch (EventNotValid|EventNotAuthorized) {
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

    /**
     * Takes a map of new event classes to legacy event classes
     *
     * @param  array<string, array<string, string>>|array<string, string>  $map
     */
    public function mapLegacyEvents(array $map): void
    {
        app(EventStore::class)->mapLegacyEvents($map);
    }
}
