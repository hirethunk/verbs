<?php

use Thunk\Verbs\Support\LeastRecentlyUsedCache;

it('implements a least-recently-used strategy', function () {
    $lru = new LeastRecentlyUsedCache(capacity: 5);

    $lru->put('a', 1)->put('b', 2)->put('c', 3)->put('d', 4)->put('e', 5);

    expect($lru->values())->toBe(['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4, 'e' => 5]);

    $lru->put('f', 6);

    expect($lru->has('a'))->toBeFalse()
        ->and($lru->has('f'))->toBeTrue()
        ->and($lru->values())->toBe(['b' => 2, 'c' => 3, 'd' => 4, 'e' => 5, 'f' => 6]);

    $lru->get('b');

    expect($lru->values())->toBe(['c' => 3, 'd' => 4, 'e' => 5, 'f' => 6, 'b' => 2]);

    $lru->put('a', 1);

    expect($lru->has('c'))->toBeFalse()
        ->and($lru->values())->toBe(['d' => 4, 'e' => 5, 'f' => 6, 'b' => 2, 'a' => 1]);

    $lru->forget('e');

    expect($lru->has('e'))->toBeFalse()
        ->and($lru->values())->toBe(['d' => 4, 'f' => 6, 'b' => 2, 'a' => 1]);
});
