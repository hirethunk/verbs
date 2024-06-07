<?php

namespace Thunk\Verbs\Support;

use Closure;

class StateInstanceCache
{
    public function __construct(
        protected int $capacity = 100,
        protected array $cache = [],
        protected ?Closure $discard_callback = null,
    ) {
    }

    public function remember(string|int $key, Closure $callback): mixed
    {
        if ($this->has($key)) {
            return $this->get($key);
        }

        $this->put($key, $value = $callback());

        return $value;
    }

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

    public function forget(string|int $key): static
    {
        if ($this->discard_callback) {
            call_user_func($this->discard_callback, $this->cache[$key]);
        }

        unset($this->cache[$key]);

        return $this;
    }

    public function prune(): static
    {
        $this->cache = array_slice($this->cache, -1 * $this->capacity, null, true);

        return $this;
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

    public function onDiscard(Closure $callback): static
    {
        $this->discard_callback = $callback;

        return $this;
    }

    protected function touch($key): void
    {
        $value = $this->cache[$key];

        unset($this->cache[$key]);

        $this->cache[$key] = $value;
    }
}
