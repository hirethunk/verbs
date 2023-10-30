<?php

namespace Thunk\Verbs\Lifecycle;

use Glhd\Bits\Snowflake;
use Illuminate\Support\LazyCollection;
use Thunk\Verbs\Models\VerbEvent;
use Thunk\Verbs\Models\VerbStateEvent;
use Thunk\Verbs\Support\EventSerializer;

class EventStore
{
    public function read(): LazyCollection
    {
        return VerbEvent::query()->lazyById();
    }

    public function write(array $events): bool
    {
        return VerbEvent::insert(static::formatForWrite($events))
            && VerbStateEvent::insert(static::formatRelationshipsForWrite($events));
    }

    protected static function formatForWrite(array $event_objects)
    {
        return array_map(fn ($event) => [
            'id' => $event->id,
            'type' => $event::class,
            'data' => app(EventSerializer::class)->serialize($event),
            'created_at' => now(),
            'updated_at' => now(),
        ], $event_objects);
    }

    protected static function formatRelationshipsForWrite(array $event_objects)
    {
        return collect($event_objects)
            ->map(fn ($event) => collect($event->states())
                ->map(fn ($state) => [
                    'id' => Snowflake::make()->id(),
                    'event_id' => $event->id,
                    'state_id' => $state->id,
                    'state_type' => $state::class,
                ])
            )
            ->flatten(1)
            ->toArray();
    }
}
