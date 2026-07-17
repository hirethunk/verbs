<?php

namespace Thunk\Verbs\Lifecycle;

use Illuminate\Support\Facades\DB;
use Throwable;
use Thunk\Verbs\CommitsImmediately;
use Thunk\Verbs\Contracts\BrokersEvents;
use Thunk\Verbs\Contracts\StoresEvents;
use Thunk\Verbs\Contracts\StoresSnapshots;
use Thunk\Verbs\Event;
use Thunk\Verbs\Exceptions\CannotReplayWithQueuedEvents;
use Thunk\Verbs\Exceptions\EventNotValid;
use Thunk\Verbs\Facades\Id;
use Thunk\Verbs\Lifecycle\Queue as EventQueue;
use Thunk\Verbs\State;
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
        protected StoresEvents $events,
        protected StoresSnapshots $snapshots,
        protected ReplayMode $replay_mode,
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
        // Events fired while history is being re-applied are ignored. During a
        // replay, the originals are already in the stream being replayed, so
        // re-firing them would duplicate (see Counter's FireOnReplayTest).
        // During a rebuild, only apply() hooks run—and apply() must be a pure
        // function of event and state—so a fire() here is a bug this protects
        // against rather than a path to support.
        if ($this->replay_mode->active()) {
            return null;
        }

        Lifecycle::run(
            event: $event,
            phases: Phases::firing(),
        );

        $this->queue->queue($event);

        // Pin the states this event touches so a prune triggered before we
        // commit can't evict them and silently reload a divergent instance.
        $event->states()->each(fn (State $state) => $this->states->pin($state));

        // Fired hooks only run once the event is queued and its states are
        // pinned: a child event fired from a hook then queues (and commits)
        // behind its parent, and a nested commit's prune can't evict the
        // parent's states mid-fire.
        Lifecycle::run(
            event: $event,
            phases: Phases::fired(),
        );

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

        try {
            foreach ($events as $event) {
                $this->metadata->setLastResults($event, $this->dispatcher->handle($event));
            }
        } finally {
            // The batch is durably stored by now, so the pins release even if
            // a handler throws—a leaked refcount would leave these states
            // unprunable for the rest of the request.
            foreach ($events as $event) {
                $event->states()->each(fn (State $state) => $this->states->unpin($state));
            }
        }

        return $this->commit();
    }

    public function replay(?callable $beforeEach = null, ?callable $afterEach = null): void
    {
        // A queued event has already applied to in-memory state but isn't
        // part of stored history yet: the replay would reset that state out
        // from under it, and a later commit would splice the event in on top
        // of the rebuilt world. Fail loudly rather than lose or double-apply.
        if ($queued = count($this->queue->getEvents())) {
            throw new CannotReplayWithQueuedEvents(sprintf(
                'Cannot replay while %s queued but uncommitted—commit or discard them before replaying.',
                $queued === 1 ? '1 event is' : "{$queued} events are",
            ));
        }

        $this->replay_mode->replaying = true;

        try {
            $this->states->reset();
            $this->states->setReplaying(true);
            $this->snapshots->reset();

            $iteration = 0;

            $this->events->read()
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
            $this->replay_mode->replaying = false;
        }
    }

    /**
     * When all of the Verbs stores share a database connection (the default),
     * the callback runs in a single atomic transaction. When snapshots live
     * elsewhere, each distinct connection gets its own nested transaction—
     * best-effort, since true cross-connection atomicity isn't possible
     * without two-phase commit.
     */
    protected function transaction(callable $callback): void
    {
        $this->warnIfStateEventsConnectionIsConfigured();

        // Comparing *resolved* names treats null and the default connection's
        // explicit name as the same connection—one transaction rather than a
        // savepoint. Checked here (not at boot) so config set at runtime,
        // e.g. in tests, is respected.
        $connections = collect([
            'events' => config('verbs.connections.events'),
            'snapshots' => config('verbs.connections.snapshots'),
        ])->map(fn ($connection) => DB::connection($connection)->getName());

        $transaction = $callback;

        // The events connection comes first, which makes it *innermost*—the
        // first to commit. A crash between cross-connection commits then
        // degrades to events-without-snapshots, which self-heals on the next
        // load. The reverse order could persist a snapshot referencing an
        // event that was never stored: permanently wrong, and invisible to
        // staleness checks.
        foreach ($connections->unique() as $connection) {
            $transaction = fn () => DB::connection($connection)->transaction($transaction);
        }

        $transaction();
    }

    /**
     * The state_events connection option is gone—mappings always share the
     * events connection—but a stale published config may still try to split them.
     */
    protected function warnIfStateEventsConnectionIsConfigured(): void
    {
        if ($this->warned_about_state_events_connection) {
            return;
        }

        $this->warned_about_state_events_connection = true;

        $state_events = config('verbs.connections.state_events');

        if ($state_events !== null
            && DB::connection($state_events)->getName() !== DB::connection(config('verbs.connections.events'))->getName()) {
            trigger_error(
                'The "verbs.connections.state_events" config option has been removed: state-event mappings always use the "events" connection.',
                E_USER_DEPRECATED,
            );
        }
    }

    /**
     * Only dirty states are written: a state is dirty when its last event id has
     * advanced past whatever was last persisted for it, and a state that never
     * saw an event at all (a blank load) never creates a snapshot row.
     */
    protected function writeSnapshots(): bool
    {
        $dirty = array_filter(
            $this->states->all(),
            function (State $state) {
                $last_event_id = Id::tryFrom($state->last_event_id);

                return $last_event_id !== null
                    && $last_event_id !== $this->metadata->getEphemeral($state, 'last_written_event_id');
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
