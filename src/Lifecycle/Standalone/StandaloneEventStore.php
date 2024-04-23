<?php

namespace App\Lifecycle\Standalone;

use Glhd\Bits\Bits;
use Illuminate\Support\LazyCollection;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Uid\AbstractUid;
use Thunk\Verbs\Contracts\StoresEvents;
use Thunk\Verbs\Facades\Id;
use Thunk\Verbs\Lifecycle\MetadataManager;
use Thunk\Verbs\State;

class StandaloneEventStore implements StoresEvents
{
    public array $events = [];
    public array $stateEvents = [];

    public function __construct(
        protected MetadataManager $metadata,
    ) {}

    public function read(
        ?State $state = null,
        Bits|UuidInterface|AbstractUid|int|string|null $after_id = null,
        bool $singleton = false,
    ): LazyCollection {
        return $this->readEvents($state, $after_id, $singleton)
            ->each(fn ($eventData) => $this->metadata->set($eventData['event'], $eventData['metadata']))
            ->map(fn ($eventData) => $eventData['event']);
    }

    public function readEvents(
        ?State $state,
        Bits|UuidInterface|AbstractUid|int|string|null $after_id,
        bool $singleton,
    ): LazyCollection {
        if ($state) {
            return LazyCollection::make(
                collect($this->stateEvents)
                    ->filter(function ($stateEvent) use ($state, $after_id, $singleton) {
                        return ($singleton ? true : $stateEvent['state_id'] === $state->id)
                            && $stateEvent['state_type'] === $state::class
                            && (is_null($after_id) ? true : $stateEvent['event_id'] > Id::from($after_id));
                    })
            );
        }

        return LazyCollection::make(
            collect($this->events)
                ->filter(function ($event) use ($after_id) {
                    return is_null($after_id) ? true : $event['id'] > Id::from($after_id);
                })
        );
    }

    public function write(array $events): bool
    {
        if (empty($events)) {
            return true;
        }

        collect($events)->each(function ($event) {
            $this->events[] = [
                'id' => Id::from($event->id),
                'type' => $event::class,
                'event' => $event,
                'metadata' => $this->metadata->get($event),
                'created_at' => $this->metadata->getEphemeral($event, 'created_at', now()),
                'updated_at' => now(),
            ];

            $event->states()->map(function ($state) use ($event) {
                $this->stateEvents[] = [
                    'id' => snowflake_id(),
                    'event_id' => Id::from($event->id),
                    'event' => $event,
                    'metadata' => $this->metadata->get($event),
                    'state_id' => Id::from($state->id),
                    'state_type' => $state::class,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            });
        });
    }
}
