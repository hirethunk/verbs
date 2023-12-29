<?php

use Glhd\Bits\Bits;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Uid\AbstractUid;
use Thunk\Verbs\State;
use Thunk\Verbs\Support\StateCollection;

test('states can be aliased', function () {
    $state1 = new class extends State
    {
        public Bits|UuidInterface|AbstractUid|int|string|null $id = 1;
    };

    $state2 = new class extends State
    {
        public Bits|UuidInterface|AbstractUid|int|string|null $id = 2;
    };

    $collection = new StateCollection([$state1, $state2]);

    expect($collection->get('state_one'))->toBeNull()
        ->and($collection->get('state1'))->toBeNull()
        ->and($collection->get('state2'))->toBeNull();

    $collection->alias('state_one', $state1);
    $collection->alias('state1', $state1);

    expect($collection->get('state_one'))->toBe($state1)
        ->and($collection->get('state1'))->toBe($state1)
        ->and($collection->get('state2'))->toBeNull();

    $collection->alias('state2', $state2);

    expect($collection->get('state_one'))->toBe($state1)
        ->and($collection->get('state1'))->toBe($state1)
        ->and($collection->get('state2'))->toBe($state2);
});
