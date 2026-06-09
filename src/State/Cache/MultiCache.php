<?php

namespace Thunk\Verbs\State\Cache;

use Thunk\Verbs\State;
use Thunk\Verbs\State\Cache\Contracts\ReadableCache;
use Thunk\Verbs\State\Cache\Contracts\WritableCache;

/**
 * A layered cache: reads fall through the layers in order (first hit wins, and
 * the hit is back-filled into the hotter layers it missed); writes fan out to
 * every writable layer. Compose it from any mix of readable/writable caches—
 * e.g. an in-memory hot tier in front of a shared read cache and a write-only
 * durability sink:
 *
 *     new MultiCache(
 *         new InMemoryCache,
 *         new ReadOnlyCache($shared),
 *         new WriteOnlyCache($durable),
 *     );
 *
 * With no layers it behaves exactly like a single InMemoryCache, which is the
 * default the container binds.
 */
class MultiCache implements ReadableCache, WritableCache
{
    /** @var array<int, ReadableCache|WritableCache> */
    protected array $layers;

    public function __construct(ReadableCache|WritableCache ...$layers)
    {
        $this->layers = $layers ?: [new InMemoryCache];
    }

    public function get(string $class, ?string $id = null): ?State
    {
        $backfill = [];

        foreach ($this->readableLayers() as $layer) {
            if ($state = $layer->get($class, $id)) {
                foreach ($backfill as $hotter) {
                    $hotter->put($state);
                }

                return $state;
            }

            if ($layer instanceof WritableCache) {
                $backfill[] = $layer;
            }
        }

        return null;
    }

    public function has(string $class, ?string $id = null): bool
    {
        foreach ($this->readableLayers() as $layer) {
            if ($layer->has($class, $id)) {
                return true;
            }
        }

        return false;
    }

    public function put(State $state): State
    {
        foreach ($this->writableLayers() as $layer) {
            $layer->put($state);
        }

        return $state;
    }

    public function pin(State|string $type, ?string $id = null): static
    {
        foreach ($this->writableLayers() as $layer) {
            $layer->pin($type, $id);
        }

        return $this;
    }

    public function unpin(State|string $type, ?string $id = null): static
    {
        foreach ($this->writableLayers() as $layer) {
            $layer->unpin($type, $id);
        }

        return $this;
    }

    public function willPrune(): bool
    {
        foreach ($this->writableLayers() as $layer) {
            if ($layer->willPrune()) {
                return true;
            }
        }

        return false;
    }

    public function prune(): static
    {
        foreach ($this->writableLayers() as $layer) {
            $layer->prune();
        }

        return $this;
    }

    public function reset(): static
    {
        foreach ($this->writableLayers() as $layer) {
            $layer->reset();
        }

        return $this;
    }

    /** @return State[] */
    public function values(): array
    {
        $values = [];

        // Earlier (hotter) layers win on key collision, so merge them last.
        foreach (array_reverse($this->readableLayers()) as $layer) {
            $values = array_replace($values, $layer->values());
        }

        return $values;
    }

    /** @return ReadableCache[] */
    protected function readableLayers(): array
    {
        return array_filter($this->layers, fn ($layer) => $layer instanceof ReadableCache);
    }

    /** @return WritableCache[] */
    protected function writableLayers(): array
    {
        return array_filter($this->layers, fn ($layer) => $layer instanceof WritableCache);
    }
}
