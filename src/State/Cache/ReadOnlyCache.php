<?php

namespace Thunk\Verbs\State\Cache;

use Thunk\Verbs\State;
use Thunk\Verbs\State\Cache\Contracts\ReadableCache;

/**
 * Wraps a cache so that, inside a MultiCache, it only ever serves reads—writes,
 * pins and prunes fan out to the other layers but skip this one. Useful for a
 * shared read tier you don't want this scope to mutate.
 */
class ReadOnlyCache implements ReadableCache
{
    public function __construct(
        protected ReadableCache $cache,
    ) {}

    public function get(string $class, ?string $id = null): ?State
    {
        return $this->cache->get($class, $id);
    }

    public function has(string $class, ?string $id = null): bool
    {
        return $this->cache->has($class, $id);
    }

    /** @return State[] */
    public function values(): array
    {
        return $this->cache->values();
    }
}
