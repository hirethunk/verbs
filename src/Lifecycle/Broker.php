<?php

namespace Thunk\Verbs\Lifecycle;

use Illuminate\Support\Facades\DB;
use Throwable;
use Thunk\Verbs\CommitsImmediately;
use Thunk\Verbs\Contracts\BrokersEvents;
use Thunk\Verbs\Contracts\StoresSnapshots;
use Thunk\Verbs\Event;
use Thunk\Verbs\Exceptions\EventNotValid;
use Thunk\Verbs\Lifecycle\Queue as EventQueue;
use Thunk\Verbs\Replay;
use Thunk\Verbs\State\StateManager;

class Broker implements BrokersEvents
{
    use BrokerConvenienceMethods;

    public bool $commit_immediately = false;

    protected bool $warned_about_state_events_connection = false;

    public function __construct(
        protected Dispatcher $dispatcher,
        protected MetadataManager $metadata,
        protected EventQueue $queue,
        protected StateManager $states,
        protected StoresSnapshots $snapshots,
    ) {}

    public function fireIfValid(Event $event): ?Event
    {
        try {
            return $this->fire($event);
        } catch (EventNotValid) {
            return null;
        }
    }

    public function fire(Event $event): ?Event
    {
        // If the user called `fire()` from inside `handle()` that event is already in the stream and
        // shouldn't be re-fired during replays. This may eventually trigger an exception, but right
        // now we'll silently discard it. Before we can drop support for handle triggering new events,
        // we'd need a way to essentially queue up actions from `handle()` that would then happen later
        // in the lifecycle, so that you could do things like "trigger this event using data from a side
        // effect triggered in handle" or "trigger this other event after this event commits" [revisit-before-1.0]
        if (app(StateManager::class)->isReapplyingHistory()) {
            return null;
        }

        Lifecycle::run($event, Phases::firing());

        $this->queue->queue($event);

        // Pin states so they're not evicted from cache (will unpin during commit)
        $event->states()->each($this->states->pin(...));

        // We need to trigger 'fired' AFTER the event has been queued and states pinned
        Lifecycle::run($event, Phases::fired());

        if ($this->commit_immediately || $event instanceof CommitsImmediately) {
            $this->commit();
        }

        return $event;
    }

    public function commit(): bool
    {
        $events = $this->queue->getEvents();

        if (empty($events)) {
            return true;
        }

        $events_are_durable = false;
        $confirm_snapshots = static fn () => null;

        try {
            $this->transaction(
                function () use (&$confirm_snapshots) {
                    $this->queue->flush();
                    $confirm_snapshots = $this->states->writeSnapshots($this->snapshots);
                },
                function (string $store) use (&$events_are_durable) {
                    if ($store === 'events') {
                        $events_are_durable = true;
                    }
                },
            );
        } catch (Throwable $exception) {
            // Re-queue the batch so it can be retried—unless the event write itself
            // committed. Split connections commit the events first, so a failure
            // between that commit and the snapshots' leaves durable events behind,
            // and re-inserting those collides on their primary key.
            if (! $events_are_durable && empty($this->queue->getEvents())) {
                $this->queue->restore($events);
            }

            throw $exception;
        }

        // Everything Verbs controls has committed, but a caller's transaction can
        // still roll all of it back—so the watermarks advance only once the write
        // is durable, or a rolled-back snapshot would look clean forever.
        $this->afterCommit(config('verbs.connections.snapshots'), $confirm_snapshots);

        // Our handlers may need to load new state/etc, so we'll allow pruning un-pinned states before
        // running them (we need to keep pinned states until after handlers run)
        $this->states->prune();

        try {
            foreach ($events as $event) {
                $this->metadata->setLastResults($event, $this->dispatcher->handle($event));
            }
        } finally {
            foreach ($events as $event) {
                $event->states()->each($this->states->unpin(...));
            }
        }

        return $this->commit();
    }

    /** @deprecated Use the Replay class directly */
    public function replay(?callable $beforeEach = null, ?callable $afterEach = null): void
    {
        Replay::full()
            ->beforeEach($beforeEach)
            ->afterEach($afterEach)
            ->run();
    }

    /**
     * When all of the Verbs stores share a database connection (the default),
     * the callback runs in a single atomic transaction. When snapshots live
     * elsewhere, each distinct connection gets its own nested transaction—
     * best-effort, since true cross-connection atomicity isn't possible
     * without two-phase commit.
     *
     * $onCommit is called with each store's key ("events"/"snapshots") as that
     * store's connection durably commits, which is how a caller tells which
     * writes survived a failure that happened between two commits.
     */
    protected function transaction(callable $callback, ?callable $onCommit = null): void
    {
        $this->warnIfStateEventsConnectionIsConfigured();

        // Event must come first so that it's the innermost closure. If events and
        // snapshots are on different connections, events succeeding and snapshots
        // failing is recoverable, but the other way around would result in data loss.
        $connections = collect([
            'events' => DB::connection(config('verbs.connections.events'))->getName(),
            'snapshots' => DB::connection(config('verbs.connections.snapshots'))->getName(),
        ]);

        foreach ($connections->unique() as $store => $connection) {
            $callback = function () use ($store, $connection, $callback, $onCommit) {
                DB::connection($connection)->transaction($callback);

                // Releasing a savepoint inside a caller's transaction isn't durable,
                // so only a return to level 0 counts as committed.
                if ($onCommit !== null && DB::connection($connection)->transactionLevel() === 0) {
                    $onCommit($store);
                }
            };
        }

        $callback();
    }

    /**
     * Run the callback once the given connection's outermost transaction has
     * actually committed—immediately, when we aren't inside one. Laravel drops
     * the callback if that transaction rolls back instead, which is exactly the
     * distinction the snapshot watermarks need: "durable", not merely "written".
     */
    protected function afterCommit(?string $name, callable $callback): void
    {
        $connection = DB::connection($name);

        if ($connection->transactionLevel() === 0) {
            $callback();

            return;
        }

        $connection->afterCommit($callback);
    }

    protected function warnIfStateEventsConnectionIsConfigured(): void
    {
        if ($this->warned_about_state_events_connection) {
            return;
        }

        $this->warned_about_state_events_connection = true;

        $state_events = config('verbs.connections.state_events');

        if (
            $state_events !== null
            && DB::connection($state_events)->getName() !== DB::connection(config('verbs.connections.events'))->getName()
        ) {
            trigger_error(
                'The "verbs.connections.state_events" config option has been removed: state-event mappings always use the "events" connection.',
                E_USER_DEPRECATED,
            );
        }
    }

    public function listen(object|string $listener): void
    {
        $this->dispatcher->register($listener);
    }

    public function commitImmediately(bool $commit_immediately = true): void
    {
        $this->commit_immediately = $commit_immediately;
    }

    public function skipPhases(Phase ...$phases): void
    {
        $this->dispatcher->skipPhases(...$phases);
    }
}
