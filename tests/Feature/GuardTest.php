<?php

use Illuminate\Auth\Access\AuthorizationException;
use Thunk\Verbs\Exceptions\EventNotValidInContext;
use Thunk\Verbs\Facades\Bus;
use Thunk\Verbs\Facades\Store;
use Thunk\Verbs\Tests\Fixtures\Events\GuardedEventWasFired;

beforeEach(function () {
    Bus::fake();
    Store::fake();
});

it('events are authorized before dispatched or stored', function () {
    GuardedEventWasFired::setUnauthorized();

    try {
        GuardedEventWasFired::fire('foo');
    } catch (\Throwable $exception) {
        Bus::assertNothingDispatchedOrReplayed();
        Store::assertNothingSaved();

        throw $exception;
    }
})->throws(AuthorizationException::class);

it('events are validated before dispatched or stored', function () {
    GuardedEventWasFired::setAuthorized();
    GuardedEventWasFired::setInvalid();

    try {
        GuardedEventWasFired::fire('foo');
    } catch (\Throwable $exception) {
        Bus::assertNothingDispatchedOrReplayed();
        Store::assertNothingSaved();

        throw $exception;
    }
})->throws(EventNotValidInContext::class);

it('if events are valid and authorized, they are stored', function () {
    GuardedEventWasFired::setAuthorized();
    GuardedEventWasFired::setValid();

    GuardedEventWasFired::fire('foo');

    Bus::assertDispatched(GuardedEventWasFired::class);
    Store::assertSaved(GuardedEventWasFired::class);
});
