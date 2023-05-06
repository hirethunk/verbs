<?php

namespace Thunk\Verbs\Attributes;

use Attribute;
use Thunk\Verbs\Events\Listener;

/**
 * @codeCoverageIgnore
 */
#[Attribute(Attribute::TARGET_METHOD)]
class Once implements ListenerAttribute
{
	public function applyToListener(Listener $listener): void
	{
		$listener->replayable = false;
	}
}
