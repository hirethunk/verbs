<?php

use Thunk\Verbs\Event;
use Thunk\Verbs\Facades\Verbs;

it('auto-commits after a job is processed', function () {
    Verbs::fake();

    Verbs::assertNothingCommitted();

    dispatch(function () {
        AutoCommitTestEvent::fire(message: 'auto-commit test');
    });

    Verbs::assertCommitted(function (AutoCommitTestEvent $event) {
        return $event->message === 'auto-commit test';
    });
});

class AutoCommitTestEvent extends Event
{
    public function __construct(public string $message)
    {
    }
}
