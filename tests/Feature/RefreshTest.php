<?php

use Illuminate\Support\Facades\DB;
use Thunk\Verbs\Attributes\Autodiscovery\AppliesToState;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;
use Thunk\Verbs\Facades\Verbs;
use Thunk\Verbs\SingletonState;
use Thunk\Verbs\State;
use Thunk\Verbs\State\StateManager;

/*
 * The identity & freshness contract: cache hits are request-stable (you
 * compute against one consistent view), and refresh() is the explicit "ask
 * otherwise"—it must return the *same instance*, brought up to date, in every
 * situation: up to date already, stale, or orphaned by a replay reset (#178).
 */

// Simulates another process committing an event this scope hasn't seen.
function commitRefreshTestEventBehindTheScenes(int $state_id): void
{
    $event_id = snowflake_id();
    $now = now()->format('Y-m-d H:i:s');

    DB::table('verb_events')->insert([
        'id' => $event_id,
        'type' => RefreshTestEvent::class,
        'data' => json_encode(['state_id' => $state_id]),
        'metadata' => '{}',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::table('verb_state_events')->insert([
        'id' => snowflake_id(),
        'event_id' => $event_id,
        'state_id' => $state_id,
        'state_type' => RefreshTestState::class,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
}

test('cache hits are request-stable: loading never re-checks staleness', function () {
    $id = snowflake_id();

    RefreshTestEvent::fire(state_id: $id);
    Verbs::commit();

    $state = RefreshTestState::load($id);

    commitRefreshTestEventBehindTheScenes($id);

    // A plain re-load returns the same, unchanged view of the world...
    expect(RefreshTestState::load($id))->toBe($state)
        ->and($state->count)->toBe(1);

    // ...until refresh() explicitly asks for the latest.
    expect($state->refresh())->toBe($state)
        ->and($state->count)->toBe(2);
});

test('refresh() on an up-to-date state runs one staleness query and no rebuild', function () {
    $id = snowflake_id();

    RefreshTestEvent::fire(state_id: $id);
    Verbs::commit();

    $state = RefreshTestState::load($id);

    $queries = 0;
    DB::listen(function () use (&$queries) {
        $queries++;
    });

    expect($state->refresh())->toBe($state)
        ->and($queries)->toBe(1);
});

test('a reference held across a replay recovers via refresh()', function () {
    $id = snowflake_id();

    RefreshTestEvent::fire(state_id: $id);
    RefreshTestEvent::fire(state_id: $id);
    Verbs::commit();

    $state = RefreshTestState::load($id);

    expect($state->count)->toBe(2);

    // The replay resets the identity map, so a different instance now owns
    // this state's identity—but the held reference must still recover.
    Verbs::replay();

    expect($state->refresh())->toBe($state)
        ->and($state->count)->toBe(2);

    // And it keeps recovering as new events arrive post-replay.
    commitRefreshTestEventBehindTheScenes($id);

    expect($state->refresh()->count)->toBe(3);
});

test('a reference held across a manual reset recovers via refresh()', function () {
    $id = snowflake_id();

    RefreshTestEvent::fire(state_id: $id);
    Verbs::commit();

    $state = RefreshTestState::load($id);

    app(StateManager::class)->reset();

    commitRefreshTestEventBehindTheScenes($id);

    // The cache has no entry at all now, so refresh() re-adopts this instance
    // as canonical and brings it up to date.
    expect($state->refresh())->toBe($state)
        ->and($state->count)->toBe(2)
        ->and(RefreshTestState::load($id))->toBe($state);
});

test('property-discovered states resolve against the current rebuild on repeat reconstitutions', function () {
    $id = snowflake_id();
    $now = now()->format('Y-m-d H:i:s');

    $insert = function () use ($id, $now) {
        $event_id = snowflake_id();

        DB::table('verb_events')->insert([
            'id' => $event_id,
            'type' => RefreshPropertyEvent::class,
            'data' => json_encode(['state' => (string) $id]),
            'metadata' => '{}',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('verb_state_events')->insert([
            'id' => snowflake_id(),
            'event_id' => $event_id,
            'state_id' => $id,
            'state_type' => RefreshPropertyState::class,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    };

    // First rebuild happens on load; the second (via refresh) re-deserializes
    // the same stored event, whose State-typed property must resolve into the
    // *new* rebuild scope—not a memoized instance from the discarded one.
    $insert();
    $state = RefreshPropertyState::load($id);
    expect($state->count)->toBe(1);

    $insert();

    expect($state->refresh()->count)->toBe(2);
});

test('refresh() works on singletons', function () {
    RefreshTestSingletonEvent::fire();
    Verbs::commit();

    $singleton = RefreshTestSingletonState::singleton();

    expect($singleton->total)->toBe(1);

    // Simulate another process committing a singleton event.
    $event_id = snowflake_id();
    $now = now()->format('Y-m-d H:i:s');

    DB::table('verb_events')->insert([
        'id' => $event_id,
        'type' => RefreshTestSingletonEvent::class,
        'data' => '{}',
        'metadata' => '{}',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::table('verb_state_events')->insert([
        'id' => snowflake_id(),
        'event_id' => $event_id,
        'state_id' => snowflake_id(), // singleton pivots carry an incidental id
        'state_type' => RefreshTestSingletonState::class,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    expect($singleton->refresh())->toBe($singleton)
        ->and($singleton->total)->toBe(2);
});

test('the identity space resets at request/job boundaries', function () {
    $id = snowflake_id();

    RefreshTestEvent::fire(state_id: $id);
    Verbs::commit();

    $state = RefreshTestState::load($id);
    $manager = app(StateManager::class);

    // Octane and queue workers flush scoped bindings between requests/jobs.
    app()->forgetScopedInstances();

    expect(app(StateManager::class))->not->toBe($manager)
        ->and(RefreshTestState::load($id))->not->toBe($state)
        ->and(RefreshTestState::load($id)->count)->toBe(1);
});

class RefreshTestState extends State
{
    public int $count = 0;
}

class RefreshTestSingletonState extends SingletonState
{
    public int $total = 0;
}

class RefreshTestEvent extends Event
{
    #[StateId(RefreshTestState::class)]
    public int $state_id;

    public function apply(RefreshTestState $state): void
    {
        $state->count++;
    }
}

class RefreshPropertyState extends State
{
    public int $count = 0;
}

class RefreshPropertyEvent extends Event
{
    public RefreshPropertyState $state;

    public function apply(RefreshPropertyState $state): void
    {
        $state->count++;
    }
}

#[AppliesToState(RefreshTestSingletonState::class)]
class RefreshTestSingletonEvent extends Event
{
    public function apply(RefreshTestSingletonState $state): void
    {
        $state->total++;
    }
}
