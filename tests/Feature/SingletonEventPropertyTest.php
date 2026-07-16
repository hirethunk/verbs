<?php

use Thunk\Verbs\Event;
use Thunk\Verbs\Facades\Verbs;
use Thunk\Verbs\Models\VerbSnapshot;
use Thunk\Verbs\SingletonState;
use Thunk\Verbs\State\StateManager;

/*
 * A singleton-typed event property serializes to the singleton's incidental
 * id, but the singleton itself is cached by type alone. Deserializing that
 * property must resolve back to the one live singleton instance—not construct
 * a second instance under a divergent key—no matter whether the lookup happens
 * during a blank rebuild, a seeded rebuild, a verify, or a replay.
 */

test('a blank rebuild resolves a singleton-typed event property across multiple events', function () {
    $singleton = SingletonPropertyState::singleton();

    SingletonPropertyEvent::fire(singleton: $singleton);
    SingletonPropertyEvent::fire(singleton: $singleton);
    Verbs::commit();

    expect(SingletonPropertyState::singleton()->count)->toBe(2);

    VerbSnapshot::query()->delete();
    app(StateManager::class)->reset();

    expect(SingletonPropertyState::singleton()->count)->toBe(2);
});

test('a blank rebuild resolves a singleton-typed event property on a single event', function () {
    $singleton = SingletonPropertyState::singleton();

    SingletonPropertyEvent::fire(singleton: $singleton);
    Verbs::commit();

    VerbSnapshot::query()->delete();
    app(StateManager::class)->reset();

    expect(SingletonPropertyState::singleton()->count)->toBe(1);
});

test('a seeded rebuild resolves a singleton-typed event property to the seed', function () {
    $singleton = SingletonPropertyState::singleton();

    $first = SingletonPropertyEvent::fire(singleton: $singleton);
    SingletonPropertyEvent::fire(singleton: $singleton);
    SingletonPropertyEvent::fire(singleton: $singleton);
    Verbs::commit();

    // Rewind the snapshot so it looks like it was last taken after the first
    // event, forcing a *seeded* rebuild over the remaining window.
    VerbSnapshot::query()
        ->where('type', SingletonPropertyState::class)
        ->update(['data' => '{"count":1}', 'last_event_id' => $first->id]);

    app(StateManager::class)->reset();

    expect(SingletonPropertyState::singleton()->count)->toBe(3);
});

test('a seeded rebuild resolves a singleton-typed event property with a single window event', function () {
    $singleton = SingletonPropertyState::singleton();

    SingletonPropertyEvent::fire(singleton: $singleton);
    $second = SingletonPropertyEvent::fire(singleton: $singleton);
    SingletonPropertyEvent::fire(singleton: $singleton);
    Verbs::commit();

    // Rewind the snapshot to just after the second event, so the seeded
    // rebuild window contains exactly one event.
    VerbSnapshot::query()
        ->where('type', SingletonPropertyState::class)
        ->update(['data' => '{"count":2}', 'last_event_id' => $second->id]);

    app(StateManager::class)->reset();

    expect(SingletonPropertyState::singleton()->count)->toBe(3);
});

test('verbs:verify passes when events carry a singleton-typed property', function () {
    $singleton = SingletonPropertyState::singleton();

    SingletonPropertyEvent::fire(singleton: $singleton);
    SingletonPropertyEvent::fire(singleton: $singleton);
    Verbs::commit();

    $this->artisan('verbs:verify')->assertExitCode(0);
});

test('replay works when events carry a singleton-typed property', function () {
    $singleton = SingletonPropertyState::singleton();

    SingletonPropertyEvent::fire(singleton: $singleton);
    SingletonPropertyEvent::fire(singleton: $singleton);
    Verbs::commit();

    Verbs::replay();

    expect(SingletonPropertyState::singleton()->count)->toBe(2);
});

class SingletonPropertyState extends SingletonState
{
    public int $count = 0;
}

class SingletonPropertyEvent extends Event
{
    public SingletonPropertyState $singleton;

    public function apply()
    {
        $this->singleton->count++;
    }
}
