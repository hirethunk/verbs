<?php

use Illuminate\Support\Facades\DB;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;
use Thunk\Verbs\Exceptions\ConcurrencyException;
use Thunk\Verbs\Facades\Id;
use Thunk\Verbs\Facades\Verbs;
use Thunk\Verbs\State;
use Thunk\Verbs\State\StateManager;

/*
 * Reconstitution only ever sees committed events, so merging its results over
 * a state with queued-but-uncommitted events would silently discard the
 * in-memory applies—and could advance last_event_id past a foreign write,
 * defeating the commit-time concurrency guard. These pin the rule that
 * in-flight states are never touched by reconstitution, while everything else
 * still refreshes in lockstep.
 */

beforeEach(function () {
    InFlightReconLog::reset();
});

// Simulates another process committing an event this scope hasn't seen, shaped
// the way EventStore::formatForWrite() would shape it.
function commitInFlightReconEventBehindTheScenes(int $state_id, int $amount): int
{
    $event_id = snowflake_id();
    $now = now()->format('Y-m-d H:i:s');

    DB::table('verb_events')->insert([
        'id' => $event_id,
        'type' => InFlightReconEvent::class,
        'data' => json_encode(['state_id' => $state_id, 'amount' => $amount]),
        'metadata' => '{}',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::table('verb_state_events')->insert([
        'id' => snowflake_id(),
        'event_id' => $event_id,
        'state_id' => $state_id,
        'state_type' => InFlightReconState::class,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    return $event_id;
}

it('never refreshes over queued-but-uncommitted applies, and surfaces the conflict at commit', function () {
    $id = snowflake_id();

    $first = InFlightReconEvent::fire(state_id: $id, amount: 10);
    Verbs::commit();

    $state = InFlightReconState::load($id);
    $queued = InFlightReconEvent::fire(state_id: $id, amount: 20);

    expect($state->total)->toBe(30);

    commitInFlightReconEventBehindTheScenes($id, 100);

    $state->refresh();

    expect($state->total)->toBe(30)
        ->and(Id::from($state->last_event_id))->toBe($queued->id);

    expect(fn () => Verbs::commit())->toThrow(ConcurrencyException::class);

    // The failed commit rolled back completely: nothing new was persisted, and
    // the snapshot never advanced past the event it actually absorbed.
    expect(DB::table('verb_events')->count())->toBe(2)
        ->and((int) DB::table('verb_snapshots')->where('state_id', $id)->value('last_event_id'))->toBe($first->id);
});

it('never merges a stale co-load over queued-but-uncommitted applies', function () {
    $id_a = snowflake_id();
    $id_b = snowflake_id();

    InFlightReconEvent::fire(state_id: $id_a, amount: 10);
    Verbs::commit();

    $state_a = InFlightReconState::load($id_a);
    $queued = InFlightReconEvent::fire(state_id: $id_a, amount: 20);

    commitInFlightReconEventBehindTheScenes($id_a, 100);

    $states = InFlightReconState::load([$id_a, $id_b]);

    expect($states[0])->toBe($state_a)
        ->and($state_a->total)->toBe(30)
        ->and(Id::from($state_a->last_event_id))->toBe($queued->id);

    expect(fn () => Verbs::commit())->toThrow(ConcurrencyException::class);
});

it('does not regress a queued state co-loaded into a rebuild, so its handler sees the right data', function () {
    $id_a = snowflake_id();
    $id_b = snowflake_id();

    // A state committed by an earlier request that this scope has never
    // loaded, so co-loading it forces a rebuild. Nothing is racing on A.
    commitInFlightReconEventBehindTheScenes($id_b, 5);

    InFlightReconEvent::fire(state_id: $id_a, amount: 10);
    Verbs::commit();

    $state_a = InFlightReconState::load($id_a);
    $queued = InFlightReconEvent::fire(state_id: $id_a, amount: 20);

    $states = InFlightReconState::load([$id_a, $id_b]);

    // The rebuild only saw A's committed events (total 10), which is *behind*
    // the live instance—merging would regress it below its own queued apply.
    expect($states[0])->toBe($state_a)
        ->and($state_a->total)->toBe(30)
        ->and(Id::from($state_a->last_event_id))->toBe($queued->id)
        ->and($states[1]->total)->toBe(5);

    Verbs::commit();

    expect(InFlightReconLog::$handled_totals)->toBe([10, 30]);

    app(StateManager::class)->reset();

    expect(InFlightReconState::load($id_a)->total)->toBe(30);
});

it('still refreshes a stale cache hit in lockstep with a co-loaded miss', function () {
    $id_a = snowflake_id();
    $id_b = snowflake_id();

    InFlightReconEvent::fire(state_id: $id_a, amount: 10);
    Verbs::commit();

    $state_a = InFlightReconState::load($id_a);

    $foreign_id = commitInFlightReconEventBehindTheScenes($id_a, 100);

    $states = InFlightReconState::load([$id_a, $id_b]);

    expect($states[0])->toBe($state_a)
        ->and($state_a->total)->toBe(110)
        ->and(Id::from($state_a->last_event_id))->toBe($foreign_id);
});

it('never reconstitutes a plain single-id load of a cached state', function () {
    $id = snowflake_id();

    InFlightReconEvent::fire(state_id: $id, amount: 10);
    Verbs::commit();

    $state = InFlightReconState::load($id);

    commitInFlightReconEventBehindTheScenes($id, 100);

    expect(InFlightReconState::load($id))->toBe($state)
        ->and($state->total)->toBe(10);
});

class InFlightReconLog
{
    public static array $handled_totals = [];

    public static function reset(): void
    {
        static::$handled_totals = [];
    }
}

class InFlightReconState extends State
{
    public int $total = 0;
}

class InFlightReconEvent extends Event
{
    #[StateId(InFlightReconState::class)]
    public int $state_id;

    public int $amount = 0;

    public function apply(InFlightReconState $state): void
    {
        $state->total += $this->amount;
    }

    public function handle(): void
    {
        InFlightReconLog::$handled_totals[] = $this->state()->total;
    }
}
