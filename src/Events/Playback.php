<?php

namespace Thunk\Verbs\Events;

class Playback
{
	public static function for(string $event_type): static
	{
		return app()->make(static::class, ['event_type' => $event_type]);
	}
	
	public static function all(): static
	{
		return app()->make(static::class);
	}
	
	public function __construct(
		protected Store $store,
		protected Dispatcher $dispatcher,
		protected ?string $event_type = null,
	) {
	}
	
	public function run()
	{
		$this->store->replay($this->dispatcher->replay(...), $this->event_type);
	}
}
