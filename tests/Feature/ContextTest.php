<?php

use Thunk\Verbs\Facades\Contexts;
use Thunk\Verbs\Facades\Store;
use Thunk\Verbs\Tests\Fixtures\Events\EventWasFired;

beforeEach(function () {
    Store::fake();
});

it('Context is applied when events are fired', function () {
    Contexts::fake();

    EventWasFired::fire('foo');

    Contexts::assertApplied(EventWasFired::class);
});
