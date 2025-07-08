<?php

use Thunk\Verbs\Examples\Counter\Events\IncrementCount;
use Thunk\Verbs\Facades\Verbs;
use Thunk\Verbs\Lifecycle\StateManager;
use Thunk\Verbs\Models\VerbEvent;
use Thunk\Verbs\Models\VerbSnapshot;

beforeEach(function () {
    Verbs::commitImmediately();
});

it('supports rehydrating a state from snapshots', function () {
    $state = IncrementCount::fire()->state();

    expect($state->count)->toBe(1);

    expect(VerbSnapshot::query()->count())->toBe(1);

    VerbEvent::truncate();

    $state = IncrementCount::fire()->state();

    expect($state->count)->toBe(2);
});

it('supports rehydrating a state from events', function () {
    $state = IncrementCount::fire()->state();

    expect($state->count)->toBe(1);

    expect(VerbEvent::query()->count())->toBe(1);

    app(StateManager::class)->reset();

    $state = IncrementCount::fire()->state();

    expect($state->count)->toBe(2);
});

it('supports rehydrating a state from a combination of snapshots and events', function () {
    expect(IncrementCount::fire()->state()->count)->toBe(1);

    expect(VerbSnapshot::query()->count())->toBe(1);
    expect(VerbEvent::query()->count())->toBe(1);

    VerbEvent::truncate();

    $snapshot = VerbSnapshot::first();

    expect(IncrementCount::fire()->state()->count)->toBe(2);

    expect(VerbEvent::query()->count())->toBe(1);

    $snapshot->save();

    expect(IncrementCount::fire()->state()->count)->toBe(3);
});
