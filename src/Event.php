<?php

namespace Thunk\Verbs;

use Glhd\Bits\Snowflake;
use Illuminate\Support\Arr;
use InvalidArgumentException;
use ReflectionMethod;
use ReflectionParameter;
use Thunk\Verbs\Support\EventSerializer;
use Thunk\Verbs\Support\PendingEvent;

abstract class Event
{
    public int|string $id;

    public bool $fired = false;

    /** @return PendingEvent<static> */
    public static function make(...$args): PendingEvent
    {
        if ((count($args) === 1 && isset($args[0]) && is_array($args[0]))) {
            $args = $args[0];
        }

        // Turn a positional array to an associative array
        if (count($args) && ! Arr::isAssoc($args)) {
            if (! method_exists(static::class, '__construct')) {
                throw new InvalidArgumentException('You cannot pass positional arguments to '.class_basename(static::class).'::make()');
            }

            // TODO: Cache this
            $names = collect((new ReflectionMethod(static::class, '__construct'))->getParameters())
                ->map(fn (ReflectionParameter $parameter) => $parameter->getName());

            $args = $names->combine(collect($args)->take($names->count()))->all();
        }

        $event = app(EventSerializer::class)->deserialize(static::class, $args);

        $event->id = Snowflake::make()->id();

        return PendingEvent::make($event);
    }

    public static function fire(...$args)
    {
        return static::make(...$args)->fire();
    }

    public function states(): array
    {
        // TODO: Use reflection and attributes to figure this out
        return [];
    }

    public function state(string $fqcn = null)
    {
        if ($fqcn) {
            return $this->states()[$fqcn] ?? null;
        }

        if (count($this->states()) === 1) {
            return $this->states()[0];
        }

        throw new InvalidArgumentException('You must specify a state class when there are multiple states');
    }
}
