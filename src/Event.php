<?php

namespace Thunk\Verbs;

use Glhd\Bits\Snowflake;
use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Thunk\Verbs\Lifecycle\Broker;

abstract class Event implements Castable
{
    public int|string $id;

    public bool $fired = false;

    public static function fire(...$args): static
    {
        $event = static::hydrate(Snowflake::make()->id(), $args);

        return app(Broker::class)->fire($event);
    }

    public static function hydrate(int|string $id, array $data): static
    {
        $event = new static();
        $event->id = $id;

        foreach ($data as $key => $value) {
            $event->{$key} = $value;
        }

        return $event;
    }

    public static function castUsing(array $arguments)
    {
        return new class implements CastsAttributes
        {
            public function get(Model $model, string $key, mixed $value, array $attributes)
            {
                // return new static();
            }

            public function set(Model $model, string $key, mixed $value, array $attributes)
            {

            }
        };
    }

    public function states(): array
    {
        // TODO: Use reflection and attributes to figure this out
        return [];
    }
}
