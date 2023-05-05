<?php

namespace Thunk\Verbs\Events;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Thunk\Verbs\Facades\Snowflake;

class Store
{
	public function insert(Event $event): string
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
	
	public function get(string $id): Event
	{
		$row = DB::table('verb_events')
			->where('id', $id)
			->first();
		
		if (! $row) {
			throw new InvalidArgumentException("Invalid event ID: {$id}");
		}
		
		return $this->hydrate($row);
	}
	
	public function replay(callable $handler, ?string $event_type = null): void
	{
		DB::table('verb_events')
			->when($event_type, fn($query) => $query->where('event_type', $event_type))
			->orderBy('id')
			->each(fn($row) => $handler($this->hydrate($row), $row));
	}
	
	protected function hydrate($row): Event
	{
		$class_name = $row->event_type;
		$payload = json_decode($row->event_data, true);
		
		return new $class_name(...$payload);
	}
}
