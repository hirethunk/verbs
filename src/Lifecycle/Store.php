<?php

namespace Thunk\Verbs\Lifecycle;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use Thunk\Verbs\Contracts\Store as StoreContract;
use Thunk\Verbs\Event;
use Thunk\Verbs\Facades\Snowflake;

class Store implements StoreContract
{
    public function save(Event $event): string
    {
        $id = Snowflake::id();

        DB::table('verb_events')
            ->insert([
                'id' => $id,
                'event_type' => $event::class,
                'event_data' => json_encode((array) $event),
            ]);

        return $id;
    }

    /** @return LazyCollection<int, \Thunk\Verbs\Event> */
    public function get(?array $event_types = null, int $chunk_size = 1000): LazyCollection
    {
        return DB::table('verb_events')
            ->when($event_types, fn ($query) => $query->whereIn('event_type', $event_types))
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
