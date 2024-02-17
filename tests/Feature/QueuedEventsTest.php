<?php

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Queue;
use Thunk\Verbs\Event;
use Thunk\Verbs\Facades\Verbs;
use Thunk\Verbs\Jobs\HandleEventJob;

beforeEach(fn () => $GLOBALS['event_log'] = []);

it('can handle queued and sync events', function () {
    Verbs::fake();
    Queue::fake();

    $sync1 = QueuedEventsTestSyncEvent::fire(message: 'sync 1');
    $queue1 = QueuedEventsTestQueuedEvent::fire(message: 'queue 1');
    $sync2 = QueuedEventsTestSyncEvent::fire(message: 'sync 2');
    $sync3 = QueuedEventsTestSyncEvent::fire(message: 'sync 3');
    $queue2 = QueuedEventsTestQueuedEvent::fire(message: 'queue 2');
    $sync4 = QueuedEventsTestSyncEvent::fire(message: 'sync 4');

    Verbs::commit();

    expect($GLOBALS['event_log'])->toBe(['sync 1', 'sync 2', 'sync 3', 'sync 4']);

    Verbs::assertCommitted(fn (QueuedEventsTestSyncEvent $event) => $event->message === 'sync 1');
    Verbs::assertCommitted(fn (QueuedEventsTestSyncEvent $event) => $event->message === 'sync 2');
    Verbs::assertCommitted(fn (QueuedEventsTestSyncEvent $event) => $event->message === 'sync 3');
    Verbs::assertCommitted(fn (QueuedEventsTestSyncEvent $event) => $event->message === 'sync 4');
    Verbs::assertCommitted(fn (QueuedEventsTestQueuedEvent $event) => $event->message === 'queue 1');
    Verbs::assertCommitted(fn (QueuedEventsTestQueuedEvent $event) => $event->message === 'queue 2');

    Queue::assertPushed(function (HandleEventJob $job) use ($queue1) {
        if ($job->event_id !== $queue1->id) {
            return false;
        }
        expect($job->replaying)->toBeFalse();

        app()->call([$job, 'handle']);

        expect($GLOBALS['event_log'])->toBe(['sync 1', 'sync 2', 'sync 3', 'sync 4', 'queue 1']);

        return true;
    });

    Queue::assertPushed(function (HandleEventJob $job) use ($queue2) {
        if ($job->event_id !== $queue2->id) {
            return false;
        }
        expect($job->replaying)->toBeFalse();

        app()->call([$job, 'handle']);

        expect($GLOBALS['event_log'])->toBe(['sync 1', 'sync 2', 'sync 3', 'sync 4', 'queue 1', 'queue 2']);

        return true;
    });
});

class QueuedEventsTestSyncEvent extends Event
{
    public function __construct(public string $message)
    {
    }

    public function handle()
    {
        $GLOBALS['event_log'][] = $this->message;
    }
}

class QueuedEventsTestQueuedEvent extends Event implements ShouldQueue
{
    public function __construct(public string $message)
    {
    }

    public function handle()
    {
        $GLOBALS['event_log'][] = $this->message;
    }
}
