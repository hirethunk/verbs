<?php

namespace Thunk\Verbs\Lifecycle;

use Thunk\Verbs\CommitsImmediately;
use Thunk\Verbs\Contracts\BrokersEvents;
use Thunk\Verbs\Contracts\StoresEvents;
use Thunk\Verbs\Contracts\StoresSnapshots;
use Thunk\Verbs\Event;
use Thunk\Verbs\Exceptions\EventNotValid;
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
        $events = $this->queue->flush();

        if (empty($events)) {
            return true;
        }

        $this->writeSnapshots();

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

    protected function writeSnapshots(): bool
    {
        return $this->snapshots->write($this->states->all());
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
