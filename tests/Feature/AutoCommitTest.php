<?php

use Thunk\Verbs\Event;
use Thunk\Verbs\Facades\Verbs;
use Thunk\Verbs\Lifecycle\AutoCommitManager;

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

it('does not auto-commit if an event throws an exception', function () {
    Verbs::fake();

    $event = new class extends Event
    {
        public function handle()
        {
            throw new RuntimeException();
        }
    };

    $thrown = false;

    try {
        verb($event);
        Verbs::commit();
    } catch (Throwable) {
        $thrown = true;
    }

    expect($thrown)->toBeTrue();

    app(AutoCommitManager::class)->commitIfAutoCommitting();

    Verbs::assertCommitCalledTimes(1);
});

it('auto-commits if an event does not throw an exception', function () {
    Verbs::fake();

    verb(new AutoCommitTestEvent('should auto-commit'));

    app(AutoCommitManager::class)->commitIfAutoCommitting();

    Verbs::assertCommitCalledTimes(1);
});

it('does not auto-commits disabled by configuration', function () {
    Verbs::fake();

    config(['verbs.autocommit' => false]);

    verb(new AutoCommitTestEvent('should not auto-commit'));

    app(AutoCommitManager::class)->commitIfAutoCommitting();

    Verbs::assertCommitCalledTimes(0);
});

class AutoCommitTestEvent extends Event
{
    public function __construct(public string $message)
    {
    }
}
