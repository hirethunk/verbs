<?php

namespace Thunk\Verbs\Lifecycle\Repositories;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use Thunk\Verbs\Contracts\EventRepository as EventRepositoryContract;
use Thunk\Verbs\Event;
use Thunk\Verbs\Facades\Snowflake;
use Thunk\Verbs\Snowflakes\Snowflake as SnowflakeInstance;

class EventRepository implements EventRepositoryContract
{
    public function save(Event $event): string
    {
        $id = Snowflake::id();

        DB::table('verb_events')
            ->insert([
                'id' => $id,
                'event_type' => $event::class,
                'event_data' => json_encode((array)$event),
            ]);

        return $id;
    }

    /** @return LazyCollection<int, \Thunk\Verbs\Event> */
    public function get(
        ?array $event_types = null,
        ?SnowflakeInstance $after = null,
        int $chunk_size = 1000,
    ): LazyCollection
    {
        return DB::table('verb_events')
            ->when($event_types, fn($query) => $query->whereIn('event_type', $event_types))
            ->when($after, fn($query) => $query->where('id', '>', $after))
            ->orderBy('id')
            ->lazy($chunk_size)
            ->map($this->hydrate(...));
    }

    protected function hydrate($row): Event
    {
        $class_name = $row->event_type;
        $payload = json_decode($row->event_data, true);

        return new $class_name(...$payload);
    }
}
