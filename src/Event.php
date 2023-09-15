<?php

namespace Thunk\Verbs;

use Glhd\Bits\Snowflake;
use Thunk\Verbs\Support\PendingEvent;

abstract class Event
{
    public int|string $id;

    public bool $fired = false;

    public static function make(): PendingEvent
    {
        $event = new static();
        $event->id = Snowflake::make()->id();

        return PendingEvent::make($event);
    }

    public static function fire(...$args): PendingEvent
    {
        return static::make()->hydrate($args)->shouldFire();
    }

    public function states(): array
    {
        // TODO: Use reflection and attributes to figure this out
        return [];
    }
}
