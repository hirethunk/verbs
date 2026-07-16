<?php

namespace Thunk\Verbs\Lifecycle;

use Illuminate\Support\Facades\DB;
use Throwable;
use Thunk\Verbs\CommitsImmediately;
use Thunk\Verbs\Contracts\BrokersEvents;
use Thunk\Verbs\Contracts\StoresEvents;
use Thunk\Verbs\Contracts\StoresSnapshots;
use Thunk\Verbs\Event;
use Thunk\Verbs\Exceptions\EventNotValid;
use Thunk\Verbs\Facades\Id;
use Thunk\Verbs\Lifecycle\Queue as EventQueue;
use Thunk\Verbs\State;
use Thunk\Verbs\State\StateManager;

class Broker implements BrokersEvents
{
    use BrokerConvenienceMethods;

    public bool $commit_immediately = false;

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
        // Events fired from within a handler while we're replaying are ignored:
        // the originals are already in the stream being replayed, so re-firing
        // them would duplicate. (See Counter's FireOnReplayTest.)
        if ($this->is_replaying) {
            return null;
        }

        Lifecycle::run(
            event: $event,
            phases: Phases::fire(),
        );

        $this->queue->queue($event);

        // Pin the states this event touches so a prune triggered before we
        // commit can't evict them and silently reload a divergent instance.
        $event->states()->each(fn (State $state) => $this->states->pin($state));

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

        // Events and snapshots persist (or fail) together: a failed snapshot
        // write can no longer leave events behind, and vice versa. Handlers
        // run *after* the transaction commits, so their side effects only ever
        // observe durably-stored events—and a handler exception can never
        // un-write them.
        try {
            $this->transaction(function () {
                $this->queue->flush();
                $this->writeSnapshots();
            });
        } catch (Throwable $exception) {
            // flush() empties the queue once the event write succeeds, so if
            // the transaction failed after that point (e.g. in the snapshot
            // write), put the batch back—a failed commit must never silently
            // drop events.
            if (empty($this->queue->getEvents())) {
                $this->queue->restore($events);
            }

            throw $exception;
        }

        // Bound the working set. The batch's states are still pinned here, so
        // they survive the prune and stay resident while their handlers run—a
        // handler that re-loads one gets the same live instance, not a divergent
        // reload of the snapshot we just wrote.
        $this->states->prune();

        foreach ($events as $event) {
            $this->metadata->setLastResults($event, $this->dispatcher->handle($event));
        }

        // Handlers have run, so the batch is fully settled—release the pins so
        // these states become evictable on the next prune.
        foreach ($events as $event) {
            $event->states()->each(fn (State $state) => $this->states->unpin($state));
        }

        return $this->commit();
    }

    public function replay(?callable $beforeEach = null, ?callable $afterEach = null): void
    {
        $this->is_replaying = true;

        try {
            $this->states->reset();
            $this->states->setReplaying(true);
            $this->snapshots->reset();

            $iteration = 0;

            app(StoresEvents::class)->read()
                ->each(function (Event $event) use ($beforeEach, $afterEach, &$iteration) {
                    if ($beforeEach) {
                        $beforeEach($event);
                    }

                    $this->dispatcher->apply($event);
                    $this->dispatcher->replay($event);

                    if ($afterEach) {
                        $afterEach($event);
                    }

                    if ($iteration++ % 500 === 0 && $this->states->willPrune()) {
                        $this->writeSnapshots();
                        $this->states->prune();
                    }
                });
        } finally {
            $this->writeSnapshots();
            $this->states->prune();
            $this->states->setReplaying(false);
            $this->is_replaying = false;
        }
    }

    /**
     * When the two stores share a database connection (the default), the
     * callback runs in a single atomic transaction. When they don't, each
     * store's writes still get their own transaction, but cross-store
     * atomicity requires a shared connection.
     */
    protected function transaction(callable $callback): void
    {
        $events_connection = config('verbs.connections.events');
        $snapshots_connection = config('verbs.connections.snapshots');

        if ($events_connection === $snapshots_connection) {
            DB::connection($events_connection)->transaction($callback);

            return;
        }

        DB::connection($events_connection)->transaction(
            fn () => DB::connection($snapshots_connection)->transaction($callback),
        );
    }

    /**
     * Only dirty states are written: a state is dirty when its position has
     * advanced past whatever was last persisted for it, and a state that never
     * saw an event at all (a blank load) never creates a snapshot row.
     */
    protected function writeSnapshots(): bool
    {
        $dirty = array_filter(
            $this->states->all(),
            function (State $state) {
                $position = Id::tryFrom($state->last_event_id);

                return $position !== null
                    && $position !== $this->metadata->getEphemeral($state, 'last_written_event_id');
            },
        );

        if (empty($dirty)) {
            return true;
        }

        if (! $this->snapshots->write(array_values($dirty))) {
            return false;
        }

        foreach ($dirty as $state) {
            $this->metadata->setEphemeral($state, 'last_written_event_id', Id::tryFrom($state->last_event_id));
        }

        return true;
    }

    public function listen(object|string $listener)
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
