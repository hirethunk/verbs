<?php

namespace Thunk\Verbs\Lifecycle;

use Thunk\Verbs\CommitsImmediately;
use Thunk\Verbs\Contracts\BrokersEvents;
use Thunk\Verbs\Contracts\StoresEvents;
use Thunk\Verbs\Event;
use Thunk\Verbs\Exceptions\EventNotValid;
use Thunk\Verbs\Lifecycle\Queue as EventQueue;

class Broker implements BrokersEvents
{
    use BrokerConvenienceMethods;

    public bool $commit_immediately = false;

    public function __construct(
        protected Dispatcher $dispatcher,
        protected MetadataManager $metadata,
        protected EventQueue $queue,
        protected StateManager $states,
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
        if ($this->is_replaying) {
            return null;
        }

        // NOTE: Any changes to how the dispatcher is called here
        // should also be applied to the `replay` method

        $this->dispatcher->boot($event);

        Guards::for($event)->check();

        $this->dispatcher->apply($event);

        $this->queue->queue($event);

        $this->dispatcher->fired($event);

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

        // FIXME: Only write changes + handle aggregate versioning

        $this->states->writeSnapshots();
        $this->states->prune();

        foreach ($events as $event) {
            $this->metadata->setLastResults($event, $this->dispatcher->handle($event));
        }

        return $this->commit();
    }

    public function replay(?callable $beforeEach = null, ?callable $afterEach = null): void
    {
        $this->is_replaying = true;

        try {
            $this->states->reset(include_storage: true);

            $iteration = 0;

            app(StoresEvents::class)->read()
                ->each(function (Event $event) use ($beforeEach, $afterEach, &$iteration) {
                    $this->states->setReplaying(true);

                    if ($beforeEach) {
                        $beforeEach($event);
                    }

                    $this->dispatcher->apply($event);
                    $this->dispatcher->replay($event);

                    if ($afterEach) {
                        $afterEach($event);
                    }

                    if ($iteration++ % 500 === 0 && $this->states->willPrune()) {
                        $this->states->writeSnapshots();
                        $this->states->prune();
                    }
                });
        } finally {
            $this->states->writeSnapshots();
            $this->states->prune();
            $this->states->setReplaying(false);
            $this->is_replaying = false;
        }
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
