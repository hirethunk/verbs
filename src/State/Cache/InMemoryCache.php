<?php

namespace Thunk\Verbs\State\Cache;

use Thunk\Verbs\SingletonState;
use Thunk\Verbs\State;
use Thunk\Verbs\State\Cache\Contracts\ReadableCache;
use Thunk\Verbs\State\Cache\Contracts\WritableCache;

class InMemoryCache implements ReadableCache, WritableCache
{
    public function __construct(
        protected int $capacity = 100,
        public array $cache = [],
    ) {}

    public function get(string $class, ?string $id = null): ?State
    {
        $key = $this->key($class, $id);

        if ($this->has($class, $id)) {
            $this->touch($key);

            return $this->cache[$key];
        }

        return null;
    }

    public function put(State $state): State
    {
        $key = $this->key($state);

        if (isset($this->cache[$key])) {
            unset($this->cache[$key]);
        }

        $this->cache[$key] = $state;

        return $state;
    }

    public function has(string $class, string $id): bool
    {
        $key = $this->key($class, $id);

        return isset($this->cache[$key]);
    }

    public function prune(): static
    {
        $this->cache = array_slice($this->cache, offset: -1 * $this->capacity, preserve_keys: true);

        return $this;
    }

    public function willPrune(): bool
    {
        return count($this->cache) > $this->capacity;
    }

    public function values(): array
    {
        return $this->cache;
    }

    public function reset(): static
    {
        $this->cache = [];

        return $this;
    }

    protected function touch($key): void
    {
        $value = $this->cache[$key];

        unset($this->cache[$key]);

        $this->cache[$key] = $value;
    }

    protected function key(State|string $type, ?string $id = null): string
    {
        // Allow passing in state objects.
        if ($type instanceof State)  {
            $id = $type instanceof SingletonState
                ? null
                : $type->id;

            $type = $type::class;
        }

        return "{$type}:{$id}";
    }
}
