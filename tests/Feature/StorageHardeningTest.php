<?php

use Glhd\Bits\Bits;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Uid\AbstractUid;
use Thunk\Verbs\Attributes\Autodiscovery\AppliesToState;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Contracts\StoresSnapshots;
use Thunk\Verbs\Event;
use Thunk\Verbs\Facades\Verbs;
use Thunk\Verbs\Lifecycle\Broker;
use Thunk\Verbs\Lifecycle\SnapshotStore;
use Thunk\Verbs\Models\VerbEvent;
use Thunk\Verbs\Models\VerbSnapshot;
use Thunk\Verbs\Models\VerbStateEvent;
use Thunk\Verbs\SingletonState;
use Thunk\Verbs\State;
use Thunk\Verbs\State\StateManager;
use Thunk\Verbs\Support\StateCollection;

test('a failing snapshot write rolls back the event insert', function () {
    app()->instance(StoresSnapshots::class, new ExplodingSnapshotStore);

    HardeningTestEvent::fire(state_id: snowflake_id());

    expect(fn () => Verbs::commit())->toThrow(RuntimeException::class);

    expect(VerbEvent::query()->count())->toBe(0)
        ->and(VerbStateEvent::query()->count())->toBe(0);
});

test('a failed commit keeps the batch queued instead of dropping it', function () {
    $exploding = new ExplodingSnapshotStore;
    app()->instance(StoresSnapshots::class, $exploding);

    $id = snowflake_id();
    HardeningTestEvent::fire(state_id: $id);

    expect(fn () => Verbs::commit())->toThrow(RuntimeException::class);

    // Once the underlying problem clears, the same batch commits cleanly.
    $exploding->explode = false;

    Verbs::commit();

    expect(VerbEvent::query()->count())->toBe(1)
        ->and(HardeningTestState::load($id)->count)->toBe(1);
});

test('a handler exception does not un-write events or snapshots', function () {
    $id = snowflake_id();

    HardeningThrowingHandlerEvent::fire(state_id: $id);

    expect(fn () => Verbs::commit())->toThrow(RuntimeException::class);

    expect(VerbEvent::query()->count())->toBe(1)
        ->and(VerbSnapshot::query()->where('state_id', $id)->count())->toBe(1);
});

test('a surrounding database transaction rolls back events and snapshots together', function () {
    try {
        DB::transaction(function () {
            HardeningTestEvent::fire(state_id: snowflake_id());
            Verbs::commit();

            throw new RuntimeException('user-land rollback');
        });
    } catch (RuntimeException) {
    }

    expect(VerbEvent::query()->count())->toBe(0)
        ->and(VerbStateEvent::query()->count())->toBe(0)
        ->and(VerbSnapshot::query()->count())->toBe(0);
});

test('commit writes snapshots only for states that advanced', function () {
    $spy = new SnapshotStoreSpy(app(SnapshotStore::class));
    app()->instance(StoresSnapshots::class, $spy);

    $a = snowflake_id();
    $b = snowflake_id();

    HardeningTestEvent::fire(state_id: $a);
    Verbs::commit();

    expect($spy->writtenStateIds())->toBe([$a]);

    // A is still live and untouched; only B advances in this batch.
    HardeningTestState::load($a);
    HardeningTestEvent::fire(state_id: $b);
    Verbs::commit();

    expect($spy->writtenStateIds())->toBe([$a, $b]);
});

test('a state hydrated from a snapshot is not re-written on the next commit', function () {
    $spy = new SnapshotStoreSpy(app(SnapshotStore::class));
    app()->instance(StoresSnapshots::class, $spy);

    $a = snowflake_id();

    HardeningTestEvent::fire(state_id: $a);
    Verbs::commit();

    app(StateManager::class)->reset();

    // Hydrates A from its snapshot (still at the persisted position)...
    HardeningTestState::load($a);

    // ...so committing an unrelated event must not re-serialize A.
    HardeningTestEvent::fire(state_id: $b = snowflake_id());
    Verbs::commit();

    expect($spy->writtenStateIds())->toBe([$a, $b]);
});

test('blank loads never create snapshot rows', function () {
    $blank_id = snowflake_id();

    HardeningTestState::load($blank_id);
    HardeningTestEvent::fire(state_id: snowflake_id());
    Verbs::commit();

    expect(VerbSnapshot::query()->where('state_id', $blank_id)->count())->toBe(0);
});

test('a corrupt snapshot falls back to rebuilding from events', function () {
    $id = snowflake_id();

    HardeningTestEvent::fire(state_id: $id);
    HardeningTestEvent::fire(state_id: $id);
    Verbs::commit();

    VerbSnapshot::query()->where('state_id', $id)->update(['data' => '{not json']);
    app(StateManager::class)->reset();

    expect(HardeningTestState::load($id)->count)->toBe(2);
});

test('a snapshot with data but no position falls back to rebuilding from events', function () {
    $id = snowflake_id();

    HardeningTestEvent::fire(state_id: $id);
    HardeningTestEvent::fire(state_id: $id);
    Verbs::commit();

    VerbSnapshot::query()->where('state_id', $id)->update(['last_event_id' => null]);
    app(StateManager::class)->reset();

    expect(HardeningTestState::load($id)->count)->toBe(2);
});

test('singleton snapshots use the sentinel id and never duplicate', function () {
    HardeningSingletonEvent::fire();
    Verbs::commit();

    $row = VerbSnapshot::query()->where('type', HardeningSingletonState::class)->sole();

    expect((int) $row->state_id)->toBe(0);

    // A fresh request re-hydrates the singleton and commits again: still one row.
    app(StateManager::class)->reset();

    HardeningSingletonEvent::fire();
    Verbs::commit();

    expect(VerbSnapshot::query()->where('type', HardeningSingletonState::class)->count())->toBe(1)
        ->and(HardeningSingletonState::singleton()->count)->toBe(2);
});

test('the natural-key migration dedupes, normalizes singletons, and drops dead rows', function () {
    config(['verbs.tables.snapshots' => 'verb_snapshots_migration_test']);

    Schema::create('verb_snapshots_migration_test', function (Blueprint $table) {
        $table->snowflakeId();
        $table->snowflake('state_id')->index();
        $table->string('type')->index();
        $table->json('data');
        $table->snowflake('last_event_id')->nullable();
        $table->timestamp('expires_at')->nullable()->index();
        $table->timestamps();
        $table->index(['state_id', 'type']);
    });

    $insert = function (string $type, int $state_id, ?int $last_event_id, string $data = '{}') {
        DB::table('verb_snapshots_migration_test')->insert([
            'id' => snowflake_id(),
            'state_id' => $state_id,
            'type' => $type,
            'data' => $data,
            'last_event_id' => $last_event_id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    };

    // Duplicate keyed rows: the most advanced one wins.
    $insert(HardeningTestState::class, 5, 10, '{"count":1}');
    $insert(HardeningTestState::class, 5, 20, '{"count":2}');

    // Duplicate singleton rows under incidental ids: normalized + deduped.
    $insert(HardeningSingletonState::class, snowflake_id(), 5, '{"count":1}');
    $insert(HardeningSingletonState::class, snowflake_id(), 15, '{"count":3}');

    // A blank-load row (no position): deleted.
    $insert(HardeningTestState::class, 6, null);

    // A type that no longer exists: left untouched.
    $insert('App\\States\\LongGone', 7, 30);

    $migration = require __DIR__.'/../../database/migrations/2026_07_15_000001_add_natural_key_to_verb_snapshots_table.php';
    $migration->up();

    $rows = DB::table('verb_snapshots_migration_test')->get();

    expect($rows)->toHaveCount(3);

    $keyed = $rows->firstWhere('type', HardeningTestState::class);
    expect((int) $keyed->state_id)->toBe(5)
        ->and((int) $keyed->last_event_id)->toBe(20);

    $singleton = $rows->firstWhere('type', HardeningSingletonState::class);
    expect((int) $singleton->state_id)->toBe(0)
        ->and((int) $singleton->last_event_id)->toBe(15);

    expect($rows->firstWhere('type', 'App\\States\\LongGone'))->not->toBeNull()
        ->and(Schema::hasColumn('verb_snapshots_migration_test', 'expires_at'))->toBeFalse();

    Schema::drop('verb_snapshots_migration_test');
});

test('the commit transaction spans every configured verbs connection', function () {
    config()->set('database.connections.verbs_secondary', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
    ]);
    config()->set('verbs.connections.state_events', 'verbs_secondary');

    $levels = [];

    // transaction() is protected, so bind into the broker to exercise the
    // connection nesting directly without faking a cross-database write.
    (function () use (&$levels) {
        $this->transaction(function () use (&$levels) {
            $levels['events'] = DB::connection(config('verbs.connections.events'))->transactionLevel();
            $levels['state_events'] = DB::connection('verbs_secondary')->transactionLevel();
        });
    })->call(app(Broker::class));

    expect($levels)->toBe(['events' => 1, 'state_events' => 1])
        ->and(DB::connection(config('verbs.connections.events'))->transactionLevel())->toBe(0)
        ->and(DB::connection('verbs_secondary')->transactionLevel())->toBe(0);
});

test('shared connections get a single commit transaction, not savepoints', function () {
    $level = null;

    (function () use (&$level) {
        $this->transaction(function () use (&$level) {
            $level = DB::connection(config('verbs.connections.events'))->transactionLevel();
        });
    })->call(app(Broker::class));

    expect($level)->toBe(1);
});

class HardeningTestState extends State
{
    public int $count = 0;
}

class HardeningSingletonState extends SingletonState
{
    public int $count = 0;
}

class HardeningTestEvent extends Event
{
    #[StateId(HardeningTestState::class)]
    public int $state_id;

    public function apply(HardeningTestState $state): void
    {
        $state->count++;
    }
}

class HardeningThrowingHandlerEvent extends Event
{
    #[StateId(HardeningTestState::class)]
    public int $state_id;

    public function apply(HardeningTestState $state): void
    {
        $state->count++;
    }

    public function handle(): void
    {
        throw new RuntimeException('handler failure');
    }
}

#[AppliesToState(HardeningSingletonState::class)]
class HardeningSingletonEvent extends Event
{
    public function apply(HardeningSingletonState $state): void
    {
        $state->count++;
    }
}

class ExplodingSnapshotStore implements StoresSnapshots
{
    public bool $explode = true;

    public function load(Bits|UuidInterface|AbstractUid|iterable|int|string $id, string $type): State|StateCollection|null
    {
        return null;
    }

    public function loadSingleton(string $type): ?State
    {
        return null;
    }

    public function positions(iterable $states): Collection
    {
        return new Collection;
    }

    public function write(array $states): bool
    {
        if ($this->explode) {
            throw new RuntimeException('snapshot store exploded');
        }

        return true;
    }

    public function reset(): bool
    {
        return true;
    }
}

class SnapshotStoreSpy implements StoresSnapshots
{
    public array $written = [];

    public function __construct(
        public StoresSnapshots $inner,
    ) {}

    public function writtenStateIds(): array
    {
        return array_map(fn (State $state) => $state->id, $this->written);
    }

    public function load(Bits|UuidInterface|AbstractUid|iterable|int|string $id, string $type): State|StateCollection|null
    {
        return $this->inner->load($id, $type);
    }

    public function loadSingleton(string $type): ?State
    {
        return $this->inner->loadSingleton($type);
    }

    public function positions(iterable $states): Collection
    {
        return $this->inner->positions($states);
    }

    public function write(array $states): bool
    {
        $this->written = array_merge($this->written, $states);

        return $this->inner->write($states);
    }

    public function reset(): bool
    {
        return $this->inner->reset();
    }
}
