<?php

namespace Thunk\Verbs;

use Thunk\Verbs\Facades\Broker;

abstract class Event
{
	public static function fire(...$args): void
	{
		Broker::fire(new static(...$args));
	}
}
