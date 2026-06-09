<?php

use Thunk\Verbs\Attributes\Autodiscovery\AppliesToState;
use Thunk\Verbs\Event;
use Thunk\Verbs\Facades\Verbs;
use Thunk\Verbs\Models\VerbSnapshot;
use Thunk\Verbs\SingletonState;
use Thunk\Verbs\State;
use Thunk\Verbs\State\Scope;

/*
 * A singleton's identity is its *type*, not its id—its in-memory id is an
 * incidental snowflake. Reconstitution must therefore discover and harvest
 * singletons by type, mirroring how their events are stored/read everywhere
 * else (see EventStore::readEvents and ReconstitutingScope::isStale). These
 * tests pin the three ways the reconstitution path used to forget that.
 */

test('a singleton reconstitutes from a stale snapshot without corrupting its identity', function () {
    $first = SingletonReconEvent::fire();
    SingletonReconEvent::fire();
    SingletonReconEvent::fire();
    Verbs::commit();

    expect(SingletonReconState::singleton()->count)->toBe(3);

    // Rewind the snapshot so it looks like it was last taken after the first event.
    VerbSnapshot::query()
        ->where('type', SingletonReconState::class)
        ->update(['data' => '{"count":1}', 'last_event_id' => $first->id]);

    app(Scope::class)->reset();

    // The stale snapshot must be brought up to date, not returned as-is.
    expect(SingletonReconState::singleton()->count)->toBe(3);

    // ...and the reconstituted singleton must keep its persisted identity, so a
    // subsequent commit updates the one snapshot row rather than inserting a
    // second (which would make ::singleton() throw forever).
    SingletonReconEvent::fire();
    Verbs::commit();

    expect(VerbSnapshot::query()->where('type', SingletonReconState::class)->count())->toBe(1)
        ->and(SingletonReconState::singleton()->count)->toBe(4);
});

test('a singleton reconstitutes from events when its snapshot is gone', function () {
    SingletonReconEvent::fire();
    SingletonReconEvent::fire();
    SingletonReconEvent::fire();
    Verbs::commit();

    VerbSnapshot::query()->where('type', SingletonReconState::class)->delete();
    app(Scope::class)->reset();

    expect(SingletonReconState::singleton()->count)->toBe(3);
});

test('reconstituting a sibling state never clobbers a live singleton', function () {
    $account_id = snowflake_id();

    SingletonReconCombinedEvent::fire(account_id: $account_id);
    SingletonReconCombinedEvent::fire(account_id: $account_id);
    Verbs::commit();

    // Drop only the account snapshot so loading it forces reconstitution of the
    // whole connected component (which includes the singleton).
    VerbSnapshot::query()->where('state_id', $account_id)->delete();
    app(Scope::class)->reset();

    // The singleton is live (and up to date) before the sibling reconstitutes.
    $live = SingletonReconGlobalState::singleton();
    expect($live->total)->toBe(20);

    SingletonReconAccountState::load($account_id);

    // The live singleton instance must survive untouched—not replaced by a
    // divergent-id rebuild—and a later commit must keep it to one snapshot row.
    expect(SingletonReconGlobalState::singleton())->toBe($live)
        ->and($live->total)->toBe(20);

    SingletonReconCombinedEvent::fire(account_id: $account_id);
    Verbs::commit();

    expect(VerbSnapshot::query()->where('type', SingletonReconGlobalState::class)->count())->toBe(1);
});

class SingletonReconState extends SingletonState
{
    public int $count = 0;
}

#[AppliesToState(SingletonReconState::class)]
class SingletonReconEvent extends Event
{
    public function apply(SingletonReconState $state): void
    {
        $state->count++;
    }
}

class SingletonReconAccountState extends State
{
    public int $balance = 0;
}

class SingletonReconGlobalState extends SingletonState
{
    public int $total = 0;
}

#[AppliesToState(state_type: SingletonReconAccountState::class, id: 'account_id')]
#[AppliesToState(state_type: SingletonReconGlobalState::class)]
class SingletonReconCombinedEvent extends Event
{
    public int $account_id;

    public function apply(SingletonReconAccountState $account, SingletonReconGlobalState $global): void
    {
        $account->balance += 10;
        $global->total += 10;
    }
}
