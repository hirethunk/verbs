<?php

use Thunk\Verbs\Support\StateInstanceCache;

it('can be pruned', function () {
    $lru = new StateInstanceCache(capacity: 5);

    $lru->put('a', 1)->put('b', 2)->put('c', 3)->put('d', 4)->put('e', 5);

    expect($lru->values())->toBe(['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4, 'e' => 5]);

    $lru->put('f', 6);

    expect($lru->values())->toBe(['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4, 'e' => 5, 'f' => 6]);

    $lru->get('b');

    expect($lru->values())->toBe(['a' => 1, 'c' => 3, 'd' => 4, 'e' => 5, 'f' => 6, 'b' => 2]);

    $lru->prune();

    expect($lru->values())->toBe(['c' => 3, 'd' => 4, 'e' => 5, 'f' => 6, 'b' => 2]);
});
