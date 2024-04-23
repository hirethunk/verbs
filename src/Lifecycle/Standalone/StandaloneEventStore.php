<?php

namespace Thunk\Verbs\Lifecycle\Standalone;

use Glhd\Bits\Bits;
use Illuminate\Support\LazyCollection;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Uid\AbstractUid;
use Thunk\Verbs\Contracts\StoresEvents;
use Thunk\Verbs\Facades\Id;
use Thunk\Verbs\Lifecycle\MetadataManager;
use Thunk\Verbs\Models\VerbEvent;
use Thunk\Verbs\Models\VerbStateEvent;
use Thunk\Verbs\State;
use Thunk\Verbs\Support\Serializer;

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
            ->each(fn (VerbEvent $model) => $this->metadata->set($model->event(), $model->metadata()))
            ->map(fn (VerbEvent $model) => $model->event());
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
                    ->map(function ($stateEvent) {
                        $stateEventData = collect($stateEvent)->except('event_data')->toArray();
                        $stateEventModel = VerbStateEvent::make($stateEventData);

                        $eventModel = VerbEvent::make($stateEvent['event_data']);
                        $stateEventModel->setRelation('event', $eventModel);

                        return $eventModel;
                    })
            );
        }

        return LazyCollection::make(
            collect($this->events)
                ->filter(function ($event) use ($after_id) {
                    return is_null($after_id) ? true : $event['id'] > Id::from($after_id);
                })
                ->map(fn ($event) => VerbEvent::make($event))
        );
    }

    public function write(array $events): bool
    {
        if (empty($events)) {
            return true;
        }

        collect($events)->each(function ($event) {
            $this->events[] = $this->formatEventForWrite($event);

            $event->states()->map(function ($state) use ($event) {
                $this->stateEvents[] = $this->formatStateEventForWrite($event, $state);
            });
        });

        return true;
    }

    public function formatEventForWrite($event): array
    {
        return [
            'id' => Id::from($event->id),
            'type' => $event::class,
            'data' => app(Serializer::class)->serialize($event),
            'metadata' => app(Serializer::class)->serialize($this->metadata->get($event)),
            'created_at' => $this->metadata->getEphemeral($event, 'created_at', now()),
            'updated_at' => now(),
        ];
    }

    public function formatStateEventForWrite($event, $state): array
    {
        return [
            'id' => snowflake_id(),
            'event_id' => Id::from($event->id),
            'state_id' => Id::from($state->id),
            'state_type' => $state::class,
            'created_at' => now(),
            'updated_at' => now(),
            'event_data' => $this->formatEventForWrite($event),
        ];
    }

    public function hydrate(array $data)
    {
        $this->events = $data['events'];
        $this->stateEvents = $data['stateEvents'];
    }

    public function dehydrate()
    {
        return [
            'events' => $this->events,
            'stateEvents' => $this->stateEvents,
        ];
    }
}
