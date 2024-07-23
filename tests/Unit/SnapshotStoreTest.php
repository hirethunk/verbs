<?php

use Glhd\Bits\Bits;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Uid\AbstractUid;
use Thunk\Verbs\Contracts\StoresSnapshots;
use Thunk\Verbs\Models\VerbSnapshot;
use Thunk\Verbs\State;

it('can store multiple different states with the same ID', function () {
    $store = app(StoresSnapshots::class);

    $state1 = new SnapshotStoreTestStateOne(1, 'state one');
    $state2 = new SnapshotStoreTestStateTwo(1, 'state two');

    $store->write([$state1, $state2]);

    $snapshot1 = VerbSnapshot::where(['type' => SnapshotStoreTestStateOne::class, 'state_id' => 1])->sole();
    $snapshot2 = VerbSnapshot::where(['type' => SnapshotStoreTestStateTwo::class, 'state_id' => 1])->sole();

    expect($snapshot1)->state()->name->toBe('state one')
        ->and($snapshot2)->state()->name->toBe('state two')
        ->and($snapshot1)->id->not()->toBe($snapshot2->id);
});

class SnapshotStoreTestStateOne extends State
{
    public function __construct(
        public Bits|UuidInterface|AbstractUid|int|string|null $id,
        public string $name,
    ) {}
}

class SnapshotStoreTestStateTwo extends State
{
    public function __construct(
        public Bits|UuidInterface|AbstractUid|int|string|null $id,
        public string $name,
    ) {}
}
