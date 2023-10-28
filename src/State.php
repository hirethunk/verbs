<?php

namespace Thunk\Verbs;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;
use Thunk\Verbs\Lifecycle\Dispatcher;
use Thunk\Verbs\Lifecycle\StateStore;

abstract class State implements Arrayable
{
    public int|string|null $id;

    public Collection $applied_events;

    public function __construct()
    {
        $this->applied_events = collect();
    }

    public static function hydrate(
        int|string $id,
        array $data = [],
        array $events = [],
    ): static {
        $state = new static();
        $state->id = $id;

        foreach ($data as $key => $value) {
            $state->{$key} = $value;
        }

        foreach ($events as $event) {
            app(Dispatcher::class)->apply($event, $state);
        }

        return $state;
    }

    public function applyData(array $data): static
    {
        foreach ($data as $key => $value) {
            $this->{$key} = $value;
        }

        return $this;
    }

    // FIXME: This function maybe needs to go away
    public static function initialize(int|string $id = null): static
    {
        return app(StateStore::class)->initialize(static::class, $id);
    }

    public static function load($from): static
    {
        $key = is_object($from) && method_exists($from, 'getVerbsStateKey')
            ? $from->getVerbsStateKey()
            : $from;

        return static::loadByKey($key);
    }

    public static function loadByKey($from): static
    {
        return app(StateStore::class)->load($from, static::class);
    }

    public function storedEvents(int|string $after_id = null)
    {
        return app(StateStore::class)->getEventsForState($this->id, static::class);
    }

    public static function singleton(): static
    {
        // FIXME: don't use "0"
        return app(StateStore::class)->load(0, static::class);
    }

    public function id(): int|string
    {
        return $this->id;
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
