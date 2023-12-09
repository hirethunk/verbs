<?php

use Thunk\Verbs\Examples\Counter\Events\DecrementCount;
use Thunk\Verbs\Facades\Verbs;

it('does not fire nested events while replaying', function () {
    $state = DecrementCount::fire()->state();

    Verbs::commit();

    // The DecrementCount handle() fires
    // ResetCount since count < 0
    expect($state->count)->toBe(0);

    Verbs::replay();

    // This time only DecrementCount fires
    expect($state->fresh()->count)->toBe(-1);
});
