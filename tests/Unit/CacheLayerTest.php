<?php

use Thunk\Verbs\State;
use Thunk\Verbs\State\Cache\InMemoryCache;

// Instantiated without the constructor so the instance isn't auto-registered
// with the app's StateManager—these tests need full control over what holds a
// reference to each state.
function cacheState(int $id): State
{
    $state = (new ReflectionClass(CacheLayerTestState::class))->newInstanceWithoutConstructor();
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

    // The pinned state (1) survives; the oldest unpinned (2) is evicted—and
    // since nothing references it anymore, it's really gone; 3 is kept.
    expect($cache->has(CacheLayerTestState::class, '1'))->toBeTrue()
        ->and($cache->has(CacheLayerTestState::class, '2'))->toBeFalse()
        ->and($cache->has(CacheLayerTestState::class, '3'))->toBeTrue();

    // Once unpinned (and no longer referenced anywhere), the state becomes
    // evictable again.
    $cache->unpin($a);
    unset($a);

    $cache->put(cacheState(4));
    $cache->prune();

    expect($cache->has(CacheLayerTestState::class, '1'))->toBeFalse();
});

test('eviction never forks identity while userland still holds a reference', function () {
    $cache = new InMemoryCache(capacity: 1);

    $held = cacheState(1);
    $cache->put($held);
    $cache->put(cacheState(2));
    $cache->prune(); // evicts 1 from the strong tier

    // The held state revives as the *same instance*, not a divergent reload...
    expect($cache->get(CacheLayerTestState::class, '1'))->toBe($held);

    // ...and a different instance still can't steal its identity.
    expect(fn () => $cache->put(cacheState(1)))->toThrow(LogicException::class);
});

test('unreferenced states are actually freed by eviction', function () {
    $cache = new InMemoryCache(capacity: 1);

    $cache->put($state = cacheState(1));
    $weak = WeakReference::create($state);
    unset($state);

    $cache->put(cacheState(2));
    $cache->prune();

    expect($weak->get())->toBeNull()
        ->and($cache->has(CacheLayerTestState::class, '1'))->toBeFalse();
});

test('pins are refcounts, not booleans', function () {
    $cache = new InMemoryCache(capacity: 0);

    $cache->put($state = cacheState(1));
    unset($state);

    $cache->pin(CacheLayerTestState::class, '1');
    $cache->pin(CacheLayerTestState::class, '1');

    // Releasing one of two pins must not expose the state to eviction.
    $cache->unpin(CacheLayerTestState::class, '1');
    $cache->prune();

    expect($cache->has(CacheLayerTestState::class, '1'))->toBeTrue();

    $cache->unpin(CacheLayerTestState::class, '1');
    $cache->prune();

    expect($cache->has(CacheLayerTestState::class, '1'))->toBeFalse();
});

test('an unbounded cache never prunes', function () {
    $cache = new InMemoryCache(capacity: null);

    foreach (range(1, 500) as $id) {
        $cache->put(cacheState($id));
    }

    expect($cache->willPrune())->toBeFalse();

    $cache->prune();

    expect($cache->values())->toHaveCount(500);
});

test('values() includes evicted-but-still-referenced states', function () {
    $cache = new InMemoryCache(capacity: 1);

    $held = cacheState(1);
    $cache->put($held);
    $cache->put($other = cacheState(2));
    $cache->prune();

    expect($cache->values())->toHaveCount(2);
});

class CacheLayerTestState extends State
{
    public int $value = 0;
}
