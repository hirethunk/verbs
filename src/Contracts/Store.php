<?php

namespace Thunk\Verbs\Contracts;

use Illuminate\Support\LazyCollection;
use Thunk\Verbs\Event;

interface Store
{
	public function save(Event $event): string;
	
	public function get(?array $event_types = null, int $chunk_size = 1000): LazyCollection;
}
