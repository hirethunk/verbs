<?php

namespace Thunk\Verbs\Support;

class StateInstanceCache
{
    public function __construct(
        protected int $capacity = 100,
        protected array $cache = [],
    ) {}

    public function get(string|int $key, mixed $default = null): mixed
    {
        if ($this->has($key)) {
            $this->touch($key);

            return $this->cache[$key];
        }

        return value($default);
    }

    public function put(string|int $key, mixed $value): static
    {
        if (isset($this->cache[$key])) {
            unset($this->cache[$key]);
        }

        $this->cache[$key] = $value;

        return $this;
    }

    public function has(string|int $key): bool
    {
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

    public function all(): array
    {
        return $this->cache;
    }

    protected function touch($key): void
    {
        $value = $this->cache[$key];

        unset($this->cache[$key]);

        $this->cache[$key] = $value;
    }
}
