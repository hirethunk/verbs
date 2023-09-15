<?php

namespace Thunk\Verbs;

use Thunk\Verbs\Lifecycle\Broker;

abstract class Event
{
    public static function fire(...$args): static
    {
        $event = new static(...$args);

        return app(Broker::class)->fire($event);
    }

    public static function hydrate(int|string $id, array $data): static
    {
        $state = new static();
        $state->id = $id;

        foreach ($data as $key => $value) {
            $state->{$key} = $value;
        }

        return $state;
    }
}
