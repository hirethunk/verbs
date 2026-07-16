<?php

namespace Thunk\Verbs\State\Cache;

use LogicException;
use Thunk\Verbs\SingletonState;
use Thunk\Verbs\State;
use Thunk\Verbs\State\Cache\Contracts\ReadableCache;
use Thunk\Verbs\State\Cache\Contracts\WritableCache;
use WeakReference;

/**
 * A bounded LRU tier in front of a weak identity map. Eviction only drops the
 * strong (LRU) reference: as long as userland holds a reference to a state, a
 * later get() revives the same instance from the weak map, so identity can
 * never fork just because we needed the memory back. States nobody references
 * are actually freed when evicted, so memory stays bounded.
 */
class InMemoryCache implements ReadableCache, WritableCache
{
    /** @var array<string, State> */
    protected array $strong = [];

    /** @var array<string, WeakReference> */
    protected array $weak = [];

    /** @var array<string, int> */
    protected array $pins = [];

    public function __construct(
        protected ?int $capacity = 100,
    ) {}

    public function get(string $class, int|string|null $id = null): ?State
    {
        $key = $this->key($class, $id);

        if (isset($this->strong[$key])) {
            $this->touch($key);

            return $this->strong[$key];
        }

        if ($state = $this->live($key)) {
            $this->strong[$key] = $state;

            return $state;
        }

        return null;
    }

    public function put(State $state): State
    {
        $key = $this->key($state);

        // One live instance per (class, id) within a scope. Re-putting the same
        // instance just refreshes its recency; putting a *different* instance
        // under an identity that's still live (even if only in userland hands)
        // would silently fork the state, so we fail loudly rather than overwrite.
        $existing = $this->strong[$key] ?? $this->live($key);

        if ($existing && $existing !== $state) {
            $class = $state::class;

            throw new LogicException("Cannot register two different [{$class}] instances for the same identity [{$key}].");
        }

        unset($this->strong[$key]);

        $this->strong[$key] = $state;
        $this->weak[$key] = WeakReference::create($state);

        return $state;
    }

    public function has(string $class, int|string|null $id = null): bool
    {
        $key = $this->key($class, $id);

        return isset($this->strong[$key]) || $this->live($key) !== null;
    }

    public function pin(State|string $type, int|string|null $id = null): static
    {
        $key = $this->key($type, $id);

        // Pins are refcounts, not booleans: an inner batch (e.g. an event fired
        // from a handler mid-commit) may pin a state the outer batch already
        // pinned, and releasing the outer pin must not expose the inner one.
        $this->pins[$key] = ($this->pins[$key] ?? 0) + 1;

        return $this;
    }

    public function unpin(State|string $type, int|string|null $id = null): static
    {
        $key = $this->key($type, $id);

        if (isset($this->pins[$key]) && --$this->pins[$key] <= 0) {
            unset($this->pins[$key]);
        }

        return $this;
    }

    public function prune(): static
    {
        // Sweep dead weak references so the identity map can't grow unbounded.
        foreach ($this->weak as $key => $reference) {
            if ($reference->get() === null) {
                unset($this->weak[$key]);
            }
        }

        if ($this->capacity === null) {
            return $this;
        }

        // Evict least-recently-used entries until we're back under capacity,
        // but never drop a pinned (in-flight) state—if the working set of pinned
        // states alone exceeds capacity, correctness wins over the memory bound.
        foreach (array_keys($this->strong) as $key) {
            if (count($this->strong) <= $this->capacity) {
                break;
            }

            if (! isset($this->pins[$key])) {
                unset($this->strong[$key]);
            }
        }

        return $this;
    }

    public function willPrune(): bool
    {
        return $this->capacity !== null && count($this->strong) > $this->capacity;
    }

    public function values(): array
    {
        $values = $this->strong;

        // States that were evicted from the strong tier but are still alive in
        // userland are still part of this scope's identity space.
        foreach ($this->weak as $key => $reference) {
            if (! isset($values[$key]) && ($state = $reference->get())) {
                $values[$key] = $state;
            }
        }

        return $values;
    }

    public function reset(): static
    {
        $this->strong = [];
        $this->weak = [];
        $this->pins = [];

        return $this;
    }

    protected function live(string $key): ?State
    {
        return isset($this->weak[$key]) ? $this->weak[$key]->get() : null;
    }

    protected function touch(string $key): void
    {
        $value = $this->strong[$key];

        unset($this->strong[$key]);

        $this->strong[$key] = $value;
    }

    protected function key(State|string $type, int|string|null $id = null): string
    {
        // Allow passing in state objects.
        if ($type instanceof State) {
            [$type, $id] = [$type::class, $type->id];
        }

        // A singleton is keyed by type alone: its in-memory id is incidental,
        // so an id passed with a singleton type must never fork the key space.
        if (is_a($type, SingletonState::class, true)) {
            $id = null;
        }

        return "{$type}:{$id}";
    }
}
