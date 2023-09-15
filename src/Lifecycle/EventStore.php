<?php

namespace Thunk\Verbs\Lifecycle;

use Illuminate\Support\LazyCollection;
use Thunk\Verbs\Models\VerbEvent;
use Thunk\Verbs\Support\Reflector;

class EventStore
{
    public function read(): LazyCollection
    {
        return VerbEvent::query()->lazyById();
    }

    public function write(array $events): bool
    {
        return VerbEvent::insert(static::formatForWrite($events));
    }

    protected static function formatForWrite(array $event_objects)
    {
        return array_map(fn ($event) => [
            'type' => $event::class,
            'data' => json_encode(
                Reflector::getNonStatePublicPropertiesAndValues($event)
            ),
            'created_at' => now(),
            'updated_at' => now(),
        ], $event_objects);
    }
}
