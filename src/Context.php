<?php

namespace Thunk\Verbs;

use Thunk\Verbs\Contracts\ManagesContext;
use Thunk\Verbs\Facades\Contexts;
use Thunk\Verbs\Snowflakes\Snowflake;

abstract class Context
{
    public ?Snowflake $last_event_id = null;
    
    public static function load(Snowflake $id): static
    {
        $context = new static($id);
        
        return Contexts::sync($context);
    }
    
    public function __construct(public Snowflake $id)
    {
    }
}
