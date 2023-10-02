<?php

namespace Thunk\Verbs\Lifecycle;

use Illuminate\Support\LazyCollection;
use Thunk\Verbs\Models\VerbEvent;
use Thunk\Verbs\Models\VerbStateEvent;
use Thunk\Verbs\Support\Reflector;

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
            'data' => json_encode(
                Reflector::getNonStatePublicPropertiesAndValues($event)
            ),
            'created_at' => now(),
            'updated_at' => now(),
        ], $event_objects);
    }

    protected static function formatRelationshipsForWrite(array $event_objects)
    {
        return collect($event_objects)
            ->map(fn ($event) => collect($event->states())
                ->map(fn ($state) => [
                    'event_id' => $event->id,
                    'state_id' => $state->id,
                    'state_type' => $state::class,
                ])
            )
            ->flatten()
            ->toArray();
    }
}
