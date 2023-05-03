<?php

namespace Thunk\Verbs\Events;

use ReflectionClass;
use Thunk\Verbs\Attributes\Once;

class Listener
{
    public static function handle($event, bool $is_replay = true)
    {
        $event_classname = get_class($event);

        $listener_reflection = new ReflectionClass(static::class);
        $listener = new static();

        foreach ($listener_reflection->getMethods() as $method) {
            if (count($method->getAttributes($event_classname))) {
                $methodName = $method->getName();
                $listener->$methodName($event);
            }

            if (count($method->getAttributes(Once::class))) {
                foreach ($method->getAttributes(Once::class) as $once) {
                    if (
                        count($once->getArguments()) === 1 &&
                        $once->getArguments()[0] === $event_classname &&
                        $is_replay
                    ) {
                        $methodName = $method->getName();
                        $listener->$methodName($event);
                    }
                }
            }
        }
    }
}