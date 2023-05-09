<?php

namespace Thunk\Verbs;

use Thunk\Verbs\Support\PendingEvent;

/**
 * @method static PendingEvent withContext(Context ...$contexts)
 * @method static void fire(...$args)
 */
abstract class Event
{
    use HasContext;

    public static function __callStatic(string $name, array $arguments)
    {
        $pending = new PendingEvent(static::class);

        return $pending->$name(...$arguments);
    }
}
