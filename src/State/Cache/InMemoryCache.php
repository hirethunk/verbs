<?php

namespace Thunk\Verbs\State\Cache;

use LogicException;
use Thunk\Verbs\SingletonState;
use Thunk\Verbs\State;
use Thunk\Verbs\State\Cache\Contracts\ReadableCache;
use Thunk\Verbs\State\Cache\Contracts\WritableCache;

class InMemoryCache implements ReadableCache, WritableCache
{
    /** @var array<string, true> */
    protected array $pinned = [];

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

        // One live instance per (class, id) within a scope. Re-putting the same
        // instance just refreshes its recency; putting a *different* instance
        // under a key that's already live would silently fork the state, so we
        // fail loudly rather than overwrite.
        if (isset($this->cache[$key]) && $this->cache[$key] !== $state) {
            $class = $state::class;

            throw new LogicException("Cannot register two different [{$class}] instances for the same identity [{$key}].");
        }

        unset($this->cache[$key]);

        $this->cache[$key] = $state;

        return $state;
    }

    public function has(string $class, ?string $id = null): bool
    {
        $key = $this->key($class, $id);

        return isset($this->cache[$key]);
    }

    public function pin(State|string $type, ?string $id = null): static
    {
        $this->pinned[$this->key($type, $id)] = true;

        return $this;
    }

    public function unpin(State|string $type, ?string $id = null): static
    {
        unset($this->pinned[$this->key($type, $id)]);

        return $this;
    }

    public function prune(): static
    {
        // Evict least-recently-used entries until we're back under capacity,
        // but never drop a pinned (in-flight) state—if the working set of pinned
        // states alone exceeds capacity, correctness wins over the memory bound.
        foreach (array_keys($this->cache) as $key) {
            if (count($this->cache) <= $this->capacity) {
                break;
            }

            if (! isset($this->pinned[$key])) {
                unset($this->cache[$key]);
            }
        }

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
        $this->pinned = [];

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
        if ($type instanceof State) {
            $id = $type instanceof SingletonState
                ? null
                : $type->id;

            $type = $type::class;
        }

        return "{$type}:{$id}";
    }
}
