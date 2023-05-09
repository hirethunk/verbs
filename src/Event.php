<?php

namespace Thunk\Verbs;

use Thunk\Verbs\Snowflakes\Snowflake;
use Thunk\Verbs\Support\PendingEvent;

/**
 * @method static PendingEvent withContext(Context ...$contexts)
 * @method static void fire(...$args)
 */
abstract class Event
{
    public Snowflake $id;
    
    public ?Snowflake $context_id = null;

    public static function __callStatic(string $name, array $arguments)
    {
        $pending = new PendingEvent(static::class);

        return $pending->$name(...$arguments);
    }
}
