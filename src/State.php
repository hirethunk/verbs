<?php

namespace Thunk\Verbs;

use Thunk\Verbs\Lifecycle\EventStore;
use Thunk\Verbs\Lifecycle\StateManager;

abstract class State
{
    public int|string|null $id = null;

    public int|string|null $last_event_id = null;

    public static function make(...$args): static
    {
        return new static(...$args);
    }

    public function __construct()
    {
        app(StateManager::class)->register($this);
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
        return app(StateManager::class)->load($from, static::class);
    }

    public static function singleton(): static
    {
        return app(StateManager::class)->singleton(static::class);
    }

    public function storedEvents()
    {
        return app(EventStore::class)
            ->read(state: $this)
            ->collect();
    }

    public function fresh(): static
    {
        return app(StateManager::class)->load($this->id, static::class);
    }
}
