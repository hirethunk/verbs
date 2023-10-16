<?php

namespace Thunk\Verbs;

use Illuminate\Contracts\Support\Arrayable;
use Thunk\Verbs\Lifecycle\StateStore;
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

    public function storedEvents()
    {
        // @todo - refactor this and make it good.
        return VerbStateEvent::where([
            'state_id' => $this->id,
            'state_type' => static::class,
        ])
            ->with('event')
            ->get()
            ->map
            ->event;
    }

    public static function loadByKey($from): static
    {
        return app(StateStore::class)->load($from, static::class);
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
