<?php

namespace Thunk\Verbs\Lifecycle;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use Thunk\Verbs\Contracts\SerializesAndRestoresEvents;
use Thunk\Verbs\Contracts\StoresEvents;
use Thunk\Verbs\Event;
use Thunk\Verbs\Facades\Snowflake;
use Thunk\Verbs\Snowflakes\Snowflake as SnowflakeInstance;

class EventRepository implements StoresEvents
{
    public function __construct(
        protected SerializesAndRestoresEvents $serializer,
    )
    {
    }

    public function save(Event $event): SnowflakeInstance
    {
        $id = Snowflake::make();

        DB::table('verb_events')
            ->insert([
                'id' => $id,
                'context_id' => $event->context_id,
                'event_type' => $event::class,
                'event_data' => $this->serializer->serializeEvent($event),
            ]);

        return $id;
    }

    /** @return LazyCollection<int, \Thunk\Verbs\Event> */
    public function get(
        ?array $event_types = null,
        ?SnowflakeInstance $context_id = null,
        ?SnowflakeInstance $after = null,
        int $chunk_size = 1000,
    ): LazyCollection
    {
        return DB::table('verb_events')
            ->when($event_types, fn($query) => $query->whereIn('event_type', $event_types))
            ->when($context_id, fn($query) => $query->where('context_id', $context_id))
            ->when($after, fn($query) => $query->where('id', '>', $after))
            ->orderBy('id')
            ->lazy($chunk_size)
            ->map(function (object $row) {
                $event = $this->serializer->deserializeEvent($row->event_type, $row->event_data);

                $event->id = Snowflake::fromId($row->id);

                if ($row->context_id) {
                    $event->context_id = Snowflake::fromId($row->context_id);
                }

                return $event;
            });
    }
}
