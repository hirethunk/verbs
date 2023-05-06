<?php

namespace Thunk\Verbs\Events;

class Broker
{
	public function __construct(
		protected Bus $bus,
		protected Store $store,
	) {
	}
	
	public function fire(Event $event): void
    {
	    Lifecycle::for($event)->authorize()->validate();
		
		$this->bus->dispatch($event);
		$this->store->insert($event);
    }
	
	public function replay(array|string $event_types = null, int $chunk_size = 1000): void
	{
		$this->store
			->get((array) $event_types, $chunk_size)
			->each($this->bus->replay(...));
	}
}
