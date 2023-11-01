<?php

namespace Thunk\Verbs\Lifecycle;

use Glhd\Bits\Snowflake;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\LazyCollection;
use Thunk\Verbs\Event;
use Thunk\Verbs\Models\VerbEvent;
use Thunk\Verbs\Models\VerbStateEvent;
use Thunk\Verbs\State;
use Thunk\Verbs\Support\EventSerializer;

class EventStore
{
    public function read(State $state = null, int|string $after_id = null, int|string $up_to_id = null): LazyCollection
    {
        if ($state) {
            return VerbStateEvent::query()
                ->with('event')
                ->where('state_id', $state->id)
                ->where('state_type', $state::class)
                ->when($after_id, fn (Builder $query) => $query->whereRelation('event', 'id', '>', $after_id))
                ->when($up_to_id, fn (Builder $query) => $query->whereRelation('event', 'id', '<=', $up_to_id))
                ->lazyById()
                ->map(fn (VerbStateEvent $pivot) => $pivot->event->event());
        }

        return VerbEvent::query()->lazyById();
    }

    public function write(array $events): bool
    {
        return VerbEvent::insert(static::formatForWrite($events))
            && VerbStateEvent::insert(static::formatRelationshipsForWrite($events));
    }

    /** @param  Event[]  $event_objects */
    protected static function formatForWrite(array $event_objects): array
    {
        return array_map(fn (Event $event) => [
            'id' => $event->id,
            'type' => $event::class,
            'data' => app(EventSerializer::class)->serialize($event),
            'created_at' => now(),
            'updated_at' => now(),
        ], $event_objects);
    }

    /** @param  Event[]  $event_objects */
    protected static function formatRelationshipsForWrite(array $event_objects): array
    {
        return collect($event_objects)
            ->flatMap(fn (Event $event) => $event->states()->map(fn ($state) => [
                'id' => Snowflake::make()->id(),
                'event_id' => $event->id,
                'state_id' => $state->id,
                'state_type' => $state::class,
            ]))
            ->all();
    }
}
