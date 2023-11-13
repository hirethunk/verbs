<?php

namespace Thunk\Verbs\Lifecycle;

use Glhd\Bits\Bits;
use Glhd\Bits\Snowflake;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\LazyCollection;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Uid\AbstractUid;
use Thunk\Verbs\Event;
use Thunk\Verbs\Facades\Verbs;
use Thunk\Verbs\Models\VerbEvent;
use Thunk\Verbs\Models\VerbStateEvent;
use Thunk\Verbs\State;
use Thunk\Verbs\Support\EventSerializer;

class EventStore
{
	public function read(
		State $state = null,
		Bits|UuidInterface|AbstractUid|int|string $after_id = null,
		Bits|UuidInterface|AbstractUid|int|string $up_to_id = null
	): LazyCollection {
		if ($state) {
			return VerbStateEvent::query()
				->with('event')
				->where('state_id', $state->id)
				->where('state_type', $state::class)
				->when($after_id, fn(Builder $query) => $query->whereRelation('event', 'id', '>', Verbs::toId($after_id)))
				->when($up_to_id, fn(Builder $query) => $query->whereRelation('event', 'id', '<=', Verbs::toId($up_to_id)))
				->lazyById()
				->map(fn(VerbStateEvent $pivot) => $pivot->event->event());
		}
		
		return VerbEvent::query()->lazyById();
	}
	
	public function write(array $events): bool
	{
		return VerbEvent::insert(static::formatForWrite($events))
			&& VerbStateEvent::insert(static::formatRelationshipsForWrite($events));
	}
	
	/** @param Event[] $event_objects */
	protected static function formatForWrite(array $event_objects): array
	{
		return array_map(fn(Event $event) => [
			'id' => Verbs::toId($event->id),
			'type' => $event::class,
			'data' => app(EventSerializer::class)->serialize($event),
			'created_at' => now(),
			'updated_at' => now(),
		], $event_objects);
	}
	
	/** @param Event[] $event_objects */
	protected static function formatRelationshipsForWrite(array $event_objects): array
	{
		return collect($event_objects)
			->flatMap(fn(Event $event) => $event->states()->map(fn($state) => [
				'id' => Snowflake::make()->id(),
				'event_id' => Verbs::toId($event->id),
				'state_id' => Verbs::toId($state->id),
				'state_type' => $state::class,
			]))
			->all();
	}
}
