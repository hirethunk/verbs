<?php

namespace Thunk\Verbs;

use Thunk\Verbs\Snowflakes\Snowflake;
use Thunk\Verbs\Support\PendingEvent;

abstract class Context
{
    public ?Snowflake $last_event_id = null;

    public static function load(int|string|Snowflake $id): static
    {
        $context = new static(Facades\Snowflake::coerce($id));

        return Facades\Contexts::sync($context);
    }

    public function __construct(public Snowflake $id)
    {
    }
    
    public function fire(Event $event): static
    {
        (new PendingEvent($event::class))->withContext($this)->fire($event);

        Facades\Contexts::sync($this);
        
        return $this;
    }
}
