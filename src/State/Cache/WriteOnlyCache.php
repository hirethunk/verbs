<?php

namespace Thunk\Verbs\State\Cache;

use Thunk\Verbs\State;
use Thunk\Verbs\State\Cache\Contracts\WritableCache;

/**
 * Wraps a cache so that, inside a MultiCache, it only ever receives writes—it
 * never serves reads or contributes to values(). Useful as a durability sink
 * (e.g. a persistent tier) that captures every mutation but whose contents are
 * re-derived through snapshots/reconstitution rather than read back directly.
 */
class WriteOnlyCache implements WritableCache
{
    public function __construct(
        protected WritableCache $cache,
    ) {}

    public function put(State $state): State
    {
        return $this->cache->put($state);
    }

    public function pin(State|string $type, ?string $id = null): static
    {
        $this->cache->pin($type, $id);

        return $this;
    }

    public function unpin(State|string $type, ?string $id = null): static
    {
        $this->cache->unpin($type, $id);

        return $this;
    }

    public function willPrune(): bool
    {
        return $this->cache->willPrune();
    }

    public function prune(): static
    {
        $this->cache->prune();

        return $this;
    }

    public function reset(): static
    {
        $this->cache->reset();

        return $this;
    }
}
