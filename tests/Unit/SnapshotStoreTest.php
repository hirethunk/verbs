<?php

use Glhd\Bits\Bits;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Uid\AbstractUid;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Contracts\StoresSnapshots;
use Thunk\Verbs\Event;
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

it('loads snapshots based on the state_id', function () {
    $id = snowflake()->make();

    SnapshotStoreTestEvent::commit(
        state_id: $id,
        name: 'event one',
    );

    $store = app(StoresSnapshots::class);

    $state = $store->load($id, SnapshotStoreTestDifferentState::class);

    expect($state)
        ->id->toBe($id->id())
        ->name->toBe('event one');

    $states = $store->load([$id], SnapshotStoreTestDifferentState::class);

    expect($states)
        ->count()->toBe(1);

    expect($states->first())
        ->id->toBe($id->id())
        ->name->toBe('event one');
});

// One identity can only occupy one row, so a write carrying two instances of it
// has to pick a winner. The furthest-ahead snapshot is the only principled
// choice—and it must not depend on where the upsert chunk boundary happens to
// fall, which is what decided it before.
it('keeps the furthest-ahead snapshot when one identity is written twice', function () {
    $store = app(StoresSnapshots::class);

    $behind = new SnapshotStoreTestStateOne(1, 'behind');
    $behind->last_event_id = 100;

    $ahead = new SnapshotStoreTestStateOne(1, 'ahead');
    $ahead->last_event_id = 200;

    $store->write([$behind, $ahead]);

    $snapshot = VerbSnapshot::where(['type' => SnapshotStoreTestStateOne::class, 'state_id' => 1])->sole();

    expect($snapshot->last_event_id)->toEqual(200)
        ->and($snapshot)->state()->name->toBe('ahead');
});

it('keeps the furthest-ahead snapshot across upsert chunks', function () {
    $store = app(StoresSnapshots::class);

    $ahead = new SnapshotStoreTestStateOne(1, 'ahead');
    $ahead->last_event_id = 200;

    $behind = new SnapshotStoreTestStateOne(1, 'behind');
    $behind->last_event_id = 100;

    // Enough padding between the two that they'd land in different chunks.
    $padding = collect(range(2, 40))->map(fn (int $id) => new SnapshotStoreTestStateOne($id, "pad {$id}"))->all();

    $store->write([$ahead, ...$padding, $behind]);

    $snapshot = VerbSnapshot::where(['type' => SnapshotStoreTestStateOne::class, 'state_id' => 1])->sole();

    expect($snapshot->last_event_id)->toEqual(200)
        ->and($snapshot)->state()->name->toBe('ahead');
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

class SnapshotStoreTestEvent extends Event
{
    #[StateId(SnapshotStoreTestDifferentState::class)]
    public Bits $state_id;

    public string $name;

    public function apply(SnapshotStoreTestDifferentState $state)
    {
        $state->name = $this->name;
    }
}

class SnapshotStoreTestDifferentState extends State
{
    public Bits|UuidInterface|AbstractUid|int|string|null $id;

    public string $name;
}
