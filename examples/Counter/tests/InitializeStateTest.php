<?php

use Thunk\Verbs\Facades\Verbs;
use Thunk\Verbs\Examples\Counter\States\CountState;
use Thunk\Verbs\Examples\Counter\Events\IncrementCount;
use Thunk\Verbs\Events\VerbsStateInitialized;
use Thunk\Verbs\Models\VerbEvent;

it('State factory initializes a state', function () {
    $count_state = CountState::factory([
        'count' => 1337
    ]);

    expect($events = VerbEvent::all())
        ->toHaveCount(1);

    expect($events->first())
        ->type->toBe(VerbsStateInitialized::class);

    expect($count_state)
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
