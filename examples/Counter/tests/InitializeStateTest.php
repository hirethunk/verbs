<?php

use Thunk\Verbs\Events\VerbsStateInitialized;
use Thunk\Verbs\Examples\Counter\Events\IncrementCount;
use Thunk\Verbs\Examples\Counter\States\CountState;
use Thunk\Verbs\Facades\Verbs;
use Thunk\Verbs\Models\VerbEvent;

it('State factory initializes a state', function () {
    $count_state = CountState::factory()->create([
        'count' => 1337,
    ]);

    expect($events = VerbEvent::all())
        ->toHaveCount(1)
        ->and($events->first())
        ->type->toBe(VerbsStateInitialized::class)
        ->and($count_state)
        ->toBeInstanceOf(CountState::class)
        ->count
        ->toBe(1337);

    IncrementCount::fire();
    Verbs::commit();

    expect($count_state)
        ->toBeInstanceOf(CountState::class)
        ->count
        ->toBe(1338);
});
