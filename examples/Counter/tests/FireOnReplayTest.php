<?php

use Thunk\Verbs\Examples\Counter\Events\IncrementCount;
use Thunk\Verbs\Examples\Counter\Events\IncrementCountTwice;
use Thunk\Verbs\Facades\Verbs;
use Thunk\Verbs\Models\VerbEvent;

it('firing events from the handle method is ignored while replaying', function () {
    IncrementCountTwice::fire();

    Verbs::commit();

    expect(VerbEvent::where('type', IncrementCount::class)->count())->toBe(2);

    Verbs::replay();

    expect(VerbEvent::where('type', IncrementCount::class)->count())->toBe(2);
});
