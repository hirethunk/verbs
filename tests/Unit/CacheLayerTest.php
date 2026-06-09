<?php

use Thunk\Verbs\State;
use Thunk\Verbs\State\Cache\InMemoryCache;
use Thunk\Verbs\State\Cache\MultiCache;
use Thunk\Verbs\State\Cache\ReadOnlyCache;
use Thunk\Verbs\State\Cache\WriteOnlyCache;

function cacheState(int $id): State
{
    $state = new CacheLayerTestState;
    $state->id = $id;

    return $state;
}

test('InMemoryCache stores and retrieves by class and id', function () {
    $cache = new InMemoryCache;
    $state = cacheState(1);

    $cache->put($state);

    expect($cache->get(CacheLayerTestState::class, '1'))->toBe($state)
        ->and($cache->has(CacheLayerTestState::class, '1'))->toBeTrue()
        ->and($cache->get(CacheLayerTestState::class, '2'))->toBeNull();
});

test('re-putting the same instance is a no-op but a different instance for the same id throws', function () {
    $cache = new InMemoryCache;
    $first = cacheState(1);

    $cache->put($first);
    $cache->put($first); // same instance: fine

    expect($cache->values())->toHaveCount(1);

    $second = cacheState(1); // different instance, same identity

    expect(fn () => $cache->put($second))->toThrow(LogicException::class);
});

test('prune evicts least-recently-used entries but never pinned ones', function () {
    $cache = new InMemoryCache(capacity: 2);

    $cache->put($a = cacheState(1));
    $cache->pin($a);
    $cache->put(cacheState(2));
    $cache->put(cacheState(3));

    expect($cache->willPrune())->toBeTrue();

    $cache->prune();

    // The pinned state (1) survives; the oldest unpinned (2) is evicted; 3 is kept.
    expect($cache->has(CacheLayerTestState::class, '1'))->toBeTrue()
        ->and($cache->has(CacheLayerTestState::class, '2'))->toBeFalse()
        ->and($cache->has(CacheLayerTestState::class, '3'))->toBeTrue();

    // Once unpinned, the state becomes evictable again.
    $cache->unpin($a);
    $cache->put(cacheState(4));
    $cache->prune();

    expect($cache->has(CacheLayerTestState::class, '1'))->toBeFalse();
});

test('MultiCache with no layers behaves like a single in-memory cache', function () {
    $cache = new MultiCache;
    $state = cacheState(1);

    $cache->put($state);

    expect($cache->get(CacheLayerTestState::class, '1'))->toBe($state)
        ->and($cache->values())->toHaveCount(1);
});

test('MultiCache reads fall through layers and back-fill the hotter ones', function () {
    $hot = new InMemoryCache;
    $cold = new InMemoryCache;
    $cache = new MultiCache($hot, $cold);

    // The state only lives in the cold tier to begin with.
    $cold->put($state = cacheState(1));
    expect($hot->has(CacheLayerTestState::class, '1'))->toBeFalse();

    // Reading through the MultiCache finds it in the cold tier and warms the hot one.
    expect($cache->get(CacheLayerTestState::class, '1'))->toBe($state)
        ->and($hot->has(CacheLayerTestState::class, '1'))->toBeTrue();
});

test('MultiCache writes fan out to every writable layer', function () {
    $hot = new InMemoryCache;
    $cold = new InMemoryCache;
    $cache = new MultiCache($hot, $cold);

    $cache->put(cacheState(1));

    expect($hot->has(CacheLayerTestState::class, '1'))->toBeTrue()
        ->and($cold->has(CacheLayerTestState::class, '1'))->toBeTrue();
});

test('a ReadOnlyCache layer serves reads but never receives writes', function () {
    $shared = new InMemoryCache;
    $shared->put(cacheState(1));

    $cache = new MultiCache(new InMemoryCache, new ReadOnlyCache($shared));

    // It contributes to reads...
    expect($cache->get(CacheLayerTestState::class, '1'))->not->toBeNull();

    // ...but writes skip it.
    $cache->put(cacheState(2));
    expect($shared->has(CacheLayerTestState::class, '2'))->toBeFalse();
});

test('a WriteOnlyCache layer receives writes but never serves reads', function () {
    $sink = new InMemoryCache;
    $cache = new MultiCache(new InMemoryCache, new WriteOnlyCache($sink));

    // Writes reach the sink...
    $cache->put(cacheState(1));
    expect($sink->has(CacheLayerTestState::class, '1'))->toBeTrue();

    // ...but its contents are never read back through the MultiCache.
    $sink->put(cacheState(2));
    expect($cache->get(CacheLayerTestState::class, '2'))->toBeNull()
        ->and($cache->values())->toHaveCount(1);
});

class CacheLayerTestState extends State
{
    public int $value = 0;
}
