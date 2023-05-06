<?php

namespace Thunk\Verbs\Attributes;

use Attribute;
use Thunk\Verbs\Events\Dispatcher\Listener;

/**
 * @codeCoverageIgnore
 */
#[Attribute(Attribute::TARGET_METHOD)]
class Listen implements ListenerAttribute
{
	public function __construct(
		protected string $event_type
	) {
	}
	
	public function applyToListener(Listener $listener): void
	{
		$listener->events[] = $this->event_type;
	}
}
