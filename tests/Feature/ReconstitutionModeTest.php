<?php

use Glhd\Bits\Bits;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Uid\AbstractUid;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Contracts\StoresSnapshots;
use Thunk\Verbs\Event;
use Thunk\Verbs\Facades\Id;
use Thunk\Verbs\Facades\Verbs;
use Thunk\Verbs\Lifecycle\SnapshotStore;
use Thunk\Verbs\Models\VerbSnapshot;
use Thunk\Verbs\State;
use Thunk\Verbs\State\StateManager;
use Thunk\Verbs\Support\StateCollection;

/*
 * Reconstitution mode routing: rebuilds are seeded from snapshots only when
 * that is provably exact (every member's snapshot at the window floor);
 * anything murkier—staggered last-event-ids, missing snapshots, or a seed that
 * changed under us—falls back to a blank baseline. Either way the resulting
 * values are identical; these tests pin both the values and the routing.
 */

function lastReconstitutionContext(): ?array
{
    $context = null;

    Log::shouldHaveReceived('debug')
        ->withArgs(function ($message, $log_context = []) use (&$context) {
            if ($message === 'Verbs: reconstituted state component.') {
                $context = $log_context;

                return true;
            }

            return false;
        })
        ->atLeast()
        ->once();

    return $context;
}

test('aligned stale snapshots reconstitute through the seeded window', function () {
    $e1 = ReconModeCombineEvent::fire(a_id: 1, b_id: 2);
    $a_at_e1 = ReconModeStateA::load(1)->value;
    $b_at_e1 = ReconModeStateB::load(2)->value;

    ReconModeBumpBEvent::fire(b_id: 2);
    ReconModeCombineEvent::fire(a_id: 1, b_id: 2);
    Verbs::commit();

    $a_truth = ReconModeStateA::load(1)->value;
    $b_truth = ReconModeStateB::load(2)->value;

    // Rewind both snapshots to their state as of e1: aligned at a common floor.
    VerbSnapshot::query()->where('state_id', 1)->update(['data' => json_encode(['value' => $a_at_e1]), 'last_event_id' => $e1->id]);
    VerbSnapshot::query()->where('state_id', 2)->update(['data' => json_encode(['value' => $b_at_e1]), 'last_event_id' => $e1->id]);

    app(StateManager::class)->reset();
    Log::spy();

    expect(ReconModeStateA::load(1)->value)->toBe($a_truth)
        ->and(ReconModeStateB::load(2)->value)->toBe($b_truth);

    $context = lastReconstitutionContext();

    expect($context['mode'])->toBe('seeded')
        ->and($context['members'])->toBe(2)
        ->and($context['window'])->toBe(2)
        ->and($context['floor'])->toBe($e1->id);
});

test('staggered snapshot last-event-ids fall back to a blank baseline', function () {
    $e1 = ReconModeCombineEvent::fire(a_id: 1, b_id: 2);
    $a_at_e1 = ReconModeStateA::load(1)->value;

    ReconModeBumpBEvent::fire(b_id: 2);
    ReconModeCombineEvent::fire(a_id: 1, b_id: 2);
    Verbs::commit();

    $a_truth = ReconModeStateA::load(1)->value;
    $b_truth = ReconModeStateB::load(2)->value;

    // Rewind only A: B's snapshot stays at head, so it has already absorbed
    // part of A's replay window and seeding cannot be exact.
    VerbSnapshot::query()->where('state_id', 1)->update(['data' => json_encode(['value' => $a_at_e1]), 'last_event_id' => $e1->id]);

    app(StateManager::class)->reset();
    Log::spy();

    expect(ReconModeStateA::load(1)->value)->toBe($a_truth)
        ->and(ReconModeStateB::load(2)->value)->toBe($b_truth);

    expect(lastReconstitutionContext()['mode'])->toBe('blank');
});

test('missing snapshots fall back to a blank baseline', function () {
    ReconModeCombineEvent::fire(a_id: 1, b_id: 2);
    ReconModeBumpBEvent::fire(b_id: 2);
    ReconModeCombineEvent::fire(a_id: 1, b_id: 2);
    Verbs::commit();

    $a_truth = ReconModeStateA::load(1)->value;
    $b_truth = ReconModeStateB::load(2)->value;

    VerbSnapshot::query()->delete();
    app(StateManager::class)->reset();
    Log::spy();

    expect(ReconModeStateA::load(1)->value)->toBe($a_truth)
        ->and(ReconModeStateB::load(2)->value)->toBe($b_truth);

    expect(lastReconstitutionContext()['mode'])->toBe('blank');
});

test('reconstitution_uses_snapshots=false forces blank rebuilds even when aligned', function () {
    config(['verbs.reconstitution_uses_snapshots' => false]);

    $e1 = ReconModeCombineEvent::fire(a_id: 1, b_id: 2);
    $a_at_e1 = ReconModeStateA::load(1)->value;
    $b_at_e1 = ReconModeStateB::load(2)->value;

    ReconModeCombineEvent::fire(a_id: 1, b_id: 2);
    Verbs::commit();

    $a_truth = ReconModeStateA::load(1)->value;

    VerbSnapshot::query()->where('state_id', 1)->update(['data' => json_encode(['value' => $a_at_e1]), 'last_event_id' => $e1->id]);
    VerbSnapshot::query()->where('state_id', 2)->update(['data' => json_encode(['value' => $b_at_e1]), 'last_event_id' => $e1->id]);

    app(StateManager::class)->reset();
    Log::spy();

    expect(ReconModeStateA::load(1)->value)->toBe($a_truth);

    expect(lastReconstitutionContext()['mode'])->toBe('blank');
});

test('a seed that advanced since planning trips the invariant and self-heals', function () {
    $e1 = ReconModeBumpBEvent::fire(b_id: 2);
    $b_at_e1 = ReconModeStateB::load(2)->value;

    ReconModeBumpBEvent::fire(b_id: 2);
    $e3 = ReconModeBumpBEvent::fire(b_id: 2);
    Verbs::commit();

    $b_truth = ReconModeStateB::load(2)->value;

    // Rewind to an aligned floor so planning chooses the seeded path...
    VerbSnapshot::query()->where('state_id', 2)->update(['data' => json_encode(['value' => $b_at_e1]), 'last_event_id' => $e1->id]);

    // ...but serve a *newer* seed than planning saw, simulating a snapshot
    // that advanced between planning and seeding.
    app()->instance(StoresSnapshots::class, new TamperingSnapshotStore(app(SnapshotStore::class), Id::from($e3->id)));

    // The scoped state manager was constructed against the real snapshot
    // store, so the tampering store only takes effect in a rebuilt scope.
    app(StateManager::class)->reset();
    app()->forgetInstance(StateManager::class);
    Log::spy();

    expect(ReconModeStateB::load(2)->value)->toBe($b_truth);

    Log::shouldHaveReceived('warning')
        ->withArgs(fn ($message, $log_context = []) => str_contains($message, 'already-absorbed event'))
        ->once();

    expect(lastReconstitutionContext()['mode'])->toBe('blank');
});

test('a hub state connected to everything stays fast when snapshots are aligned', function () {
    $accounts = collect(range(1, 30))->map(fn () => snowflake_id());

    $values = [];

    foreach (range(0, 194) as $i) {
        $account_id = $accounts[$i % $accounts->count()];
        $event = ReconModeHubEvent::fire(hub_id: 1_000_000, account_id: $account_id);
    }

    $floor_event = $event;

    // Capture every live value as of the floor event.
    foreach ($accounts as $account_id) {
        $values[$account_id] = ReconModeHubAccountState::load($account_id)->total;
    }
    $hub_at_floor = ReconModeHubState::load(1_000_000)->total;

    $tail_accounts = $accounts->take(3);

    foreach ($tail_accounts as $account_id) {
        ReconModeHubEvent::fire(hub_id: 1_000_000, account_id: $account_id);
    }

    Verbs::commit();

    $truth = ReconModeHubAccountState::load($tail_accounts[0])->total;
    $hub_truth = ReconModeHubState::load(1_000_000)->total;

    // Freeze every snapshot at the common floor: data-as-of-floor, last_event_id=floor.
    foreach ($accounts as $account_id) {
        VerbSnapshot::query()->where('state_id', $account_id)->update([
            'data' => json_encode(['total' => $values[$account_id]]),
            'last_event_id' => $floor_event->id,
        ]);
    }

    VerbSnapshot::query()->where('type', ReconModeHubState::class)->update([
        'data' => json_encode(['total' => $hub_at_floor]),
        'last_event_id' => $floor_event->id,
    ]);

    app(StateManager::class)->reset();
    Log::spy();

    expect(ReconModeHubAccountState::load($tail_accounts[0])->total)->toBe($truth)
        ->and(ReconModeHubState::load(1_000_000)->total)->toBe($hub_truth);

    $context = lastReconstitutionContext();

    // Only the 3-event tail replays, and only the states it touches join the
    // component—not the other 27 accounts.
    expect($context['mode'])->toBe('seeded')
        ->and($context['window'])->toBe(3)
        ->and($context['members'])->toBeLessThanOrEqual(4);
});

class ReconModeStateA extends State
{
    public int $value = 0;
}

class ReconModeStateB extends State
{
    public int $value = 0;
}

class ReconModeCombineEvent extends Event
{
    #[StateId(ReconModeStateA::class)]
    public int $a_id;

    #[StateId(ReconModeStateB::class)]
    public int $b_id;

    public function apply(ReconModeStateA $a, ReconModeStateB $b): void
    {
        $a->value = $a->value * 3 + $b->value + 1;
        $b->value = $b->value * 2 + $a->value;
    }
}

class ReconModeBumpBEvent extends Event
{
    #[StateId(ReconModeStateB::class)]
    public int $b_id;

    public function apply(ReconModeStateB $b): void
    {
        $b->value += 7;
    }
}

class ReconModeHubState extends State
{
    public int $total = 0;
}

class ReconModeHubAccountState extends State
{
    public int $total = 0;
}

class ReconModeHubEvent extends Event
{
    #[StateId(ReconModeHubState::class)]
    public int $hub_id;

    #[StateId(ReconModeHubAccountState::class)]
    public int $account_id;

    public function apply(ReconModeHubState $hub, ReconModeHubAccountState $account): void
    {
        $hub->total++;
        $account->total = $account->total * 2 + $hub->total;
    }
}

class TamperingSnapshotStore implements StoresSnapshots
{
    public function __construct(
        public StoresSnapshots $inner,
        public int|string $tampered_last_event_id,
    ) {}

    public function load(Bits|UuidInterface|AbstractUid|iterable|int|string $id, string $type): State|StateCollection|null
    {
        $result = $this->inner->load($id, $type);

        // Seed loads pass iterable ids; initial hydration passes scalars.
        if (is_iterable($id) && $result instanceof StateCollection) {
            $result->each(fn (State $state) => $state->last_event_id = $this->tampered_last_event_id);
        }

        return $result;
    }

    public function loadSingleton(string $type): ?State
    {
        return $this->inner->loadSingleton($type);
    }

    public function hydrateLastEventIds(iterable $states): Collection
    {
        return $this->inner->hydrateLastEventIds($states);
    }

    public function write(array $states): bool
    {
        return $this->inner->write($states);
    }

    public function reset(): bool
    {
        return $this->inner->reset();
    }
}
