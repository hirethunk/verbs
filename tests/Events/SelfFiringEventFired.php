<?php

namespace Thunk\Verbs\Tests\Events;

use Thunk\Verbs\Event;

class SelfFiringEventFired extends Event
{
	public function __construct(
		public string $name
	) {
	}
	
	public function onFire()
	{
		$GLOBALS['heard_events'][] = "self-always:{$this->name}";
	}
}
