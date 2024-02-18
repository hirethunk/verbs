<?php

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Queue;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;
use Thunk\Verbs\Facades\Verbs;
use Thunk\Verbs\Jobs\HandleEventJob;
use Thunk\Verbs\State;

beforeEach(fn () => $GLOBALS['event_log'] = []);

it('can handle queued and sync events', function () {
    Verbs::fake();
    Queue::fake();

    $sync1 = QueuedEventsTestSyncEvent::fire(state_id: 1, message: 'sync 1');
    $queue1 = QueuedEventsTestQueuedEvent::fire(state_id: 1, message: 'queue 1');
    $sync2 = QueuedEventsTestSyncEvent::fire(state_id: 1, message: 'sync 2');
    $sync3 = QueuedEventsTestSyncEvent::fire(state_id: 1, message: 'sync 3');
    $queue2 = QueuedEventsTestQueuedEvent::fire(state_id: 1, message: 'queue 2');
    $sync4 = QueuedEventsTestSyncEvent::fire(state_id: 1, message: 'sync 4');

    expect($sync1->state(QueuedEventsTestState::class)->apply_count)->toBe(6);

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
        expect($job->replaying)->toBeFalse()
            ->and($event->state(QueuedEventsTestState::class)->apply_count)->toBe(2);

        app()->call([$job, 'handle']);

        expect($GLOBALS['event_log'])->toBe(['sync 1', 'sync 2', 'sync 3', 'sync 4', 'queue 1']);

        return true;
    });

    Queue::assertPushed(function (HandleEventJob $job) use ($queue2) {
        if ($job->event_id !== $queue2->id) {
            return false;
        }
        expect($job->replaying)->toBeFalse()
            ->and($event->state(QueuedEventsTestState::class)->apply_count)->toBe(5);

        app()->call([$job, 'handle']);

        expect($GLOBALS['event_log'])->toBe(['sync 1', 'sync 2', 'sync 3', 'sync 4', 'queue 1', 'queue 2']);

        return true;
    });
});

class QueuedEventsTestSyncEvent extends Event
{
    public function __construct(
        #[StateId(QueuedEventsTestState::class)] public int $state_id,
        public string $message
    ) {
    }

    public function apply(QueuedEventsTestState $state)
    {
        $state->apply_count++;
    }

    public function handle()
    {
        $GLOBALS['event_log'][] = $this->message;
    }
}

class QueuedEventsTestQueuedEvent extends QueuedEventsTestSyncEvent implements ShouldQueue
{
}

class QueuedEventsTestState extends State
{
    public int $apply_count = 0;
}
