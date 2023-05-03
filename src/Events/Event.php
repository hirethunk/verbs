<?php

namespace Thunk\Verbs\Events;

use Illuminate\Support\Facades\DB;
use Thunk\Verbs\Facades\Snowflake;

class Event
{
    public static function fire (ListenerRegistry $registry = null): bool
    {
        if ($registry) {
            $registry->passEventToListeners(new static());
        }

        DB::table('events')->insert([
            'id' => Snowflake::id(),
            'event_type' => static::class,
            'event_data' => json_encode([]),
        ]);

        return true;
    }

    public static function replay (ListenerRegistry $registry): void
    {
        DB::table('events')
            ->get()
            ->each(
                fn ($event_row) => $registry->passEventToListeners(
                    new $event_row->event_type(),
                    true
                )
            );
    }
}