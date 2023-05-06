<?php

namespace Thunk\Verbs\Lifecycle;

use Thunk\Verbs\Contracts;
use Thunk\Verbs\Event;

class Broker implements Contracts\Broker
{
    public function __construct(
        protected Contracts\Bus $bus,
        protected Contracts\Store $store,
    ) {
    }

    public function fire(Event $event): void
    {
        Guards::for($event)->check();

        $this->bus->dispatch($event);
        $this->store->save($event);
    }

    public function replay(array|string $event_types = null, int $chunk_size = 1000): void
    {
        $this->store
	        ->get((array) $event_types, $chunk_size)
	        ->each($this->bus->replay(...));
    }
}
