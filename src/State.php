<?php

namespace Thunk\Verbs;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Carbon;
use Thunk\Verbs\Lifecycle\Dispatcher;
use Thunk\Verbs\Lifecycle\StateStore;
use Thunk\Verbs\Models\VerbSnapshot;
use Thunk\Verbs\Models\VerbStateEvent;

abstract class State implements Arrayable
{
    public int|string|null $id;

    public static function hydrate(
        int|string $id,
        array $data,
    ): static {
        $state = new static();
        $state->id = $id;

        foreach ($data as $key => $value) {
            $state->{$key} = $value;
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

    public static function hydrateFromStoredEvents(
        int|string $id,
        ?VerbSnapshot $latest_snapshot = null
    ): static
    {
        $state = new static();
        $state->id = $id;

        $cutoff_id = $latest_snapshot?->id;

        $events = $state->storedEvents($cutoff_id);
        
        if ($latest_snapshot) {
            $state->applyData($latest_snapshot->data);
        }

        foreach ($events as $event) {
            app(Dispatcher::class)->apply($event, $state);
        }

        return $state;
    }

    public function storedEvents(int|string|null $after_id = null)
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
