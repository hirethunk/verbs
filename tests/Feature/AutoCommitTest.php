<?php

use Thunk\Verbs\Event;
use Thunk\Verbs\Facades\Verbs;

it('auto-commits after a job is processed', function () {
    Verbs::fake();

    Verbs::assertNothingCommitted();

    dispatch(function () {
        AutoCommitTestEvent::fire(message: 'auto-commit job test');
    });

    Verbs::assertCommitted(function (AutoCommitTestEvent $event) {
        return $event->message === 'auto-commit job test';
    });
});

it('auto-commits before a DB transaction commits', function () {
    Verbs::fake();

    Verbs::assertNothingCommitted();

    DB::transaction(fn () => AutoCommitTestEvent::fire(message: 'auto-commit db test'));

    Verbs::assertCommitted(function (AutoCommitTestEvent $event) {
        return $event->message === 'auto-commit db test';
    });
});

class AutoCommitTestEvent extends Event
{
    public function __construct(public string $message)
    {
    }
}
