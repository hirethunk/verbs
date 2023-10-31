<?php

namespace Thunk\Verbs;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;
use Thunk\Verbs\Lifecycle\EventStore;
use Thunk\Verbs\Lifecycle\StateRegistry;

abstract class State implements Arrayable
{
    public int|string|null $id;

    public Collection $applied_events;

    public static function make(...$args): static
    {
        return new static(...$args);
    }

    public function __construct()
    {
        $this->applied_events = collect();

        app(StateRegistry::class)->register($this);
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
        return app(StateRegistry::class)->load($from, static::class);
    }

    public function storedEvents(int|string $after_id = null)
    {
        return app(EventStore::class)->read(state: $this)->collect();
    }

    public static function singleton(): static
    {
        // FIXME: don't use "0"
        return app(StateRegistry::class)->load(0, static::class);
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
