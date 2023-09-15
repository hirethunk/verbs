<?php

namespace Thunk\Verbs\Lifecycle;

use Thunk\Verbs\Support\Reflector;
use Thunk\Verbs\VerbEvent;

class EventStore
{
    public function write(array $events)
    {
        return VerbEvent::insert(
            self::formatForWrite($events)
        );
    }

    protected static function formatForWrite(array $event_objects)
    {
        return array_map(
            function ($event) {
                $event_properties_as_json = json_encode(
                    Reflector::getNonStatePublicPropertiesAndValues($event)
                );

                return [
                    'type' => $event::class,
                    'data' => $event_properties_as_json,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            },
            $event_objects
        );
    }
}
