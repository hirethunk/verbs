<?php

namespace Thunk\Verbs\Lifecycle;

use Thunk\Verbs\CommitsImmediately;
use Thunk\Verbs\Contracts\BrokersEvents;
use Thunk\Verbs\Contracts\StoresEvents;
use Thunk\Verbs\Contracts\StoresSnapshots;
use Thunk\Verbs\Event;
use Thunk\Verbs\Exceptions\EventNotValid;
use Thunk\Verbs\Lifecycle\Queue as EventQueue;
use Thunk\Verbs\Support\EventStateRegistry;
use Thunk\Verbs\Support\Wormhole;

class Broker implements BrokersEvents
{
    use BrokerConvenienceMethods;

    public bool $commit_immediately = false;

    public AutoCommitManager $auto_commit_manager;

    public function __construct(
        public Dispatcher $dispatcher,
        public MetadataManager $metadata,
        public Wormhole $wormhole,
        public EventStateRegistry $event_state_registry,
        public ?StoresEvents $event_store,
        public ?EventQueue $event_queue,
        public ?StoresSnapshots $snapshot_store,
        public ?StateManager $state_manager,
    ) {
    }

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

        $states = $event->states();

        $states->each(fn ($state) => Guards::for($this->dispatcher, $event, $state)->check());

        Guards::for($this->dispatcher, $event, null)->check();

        $states->each(fn ($state) => $this->dispatcher->apply($event, $state));

        $this->event_queue->queue($event);

        $this->dispatcher->fired($event, $states);

        if ($this->commit_immediately || $event instanceof CommitsImmediately) {
            $this->commit();
        }

        return $event;
    }

    public function commit(): bool
    {
        $events = $this->event_queue->flush();

        if (empty($events)) {
            return true;
        }

        // FIXME: Only write changes + handle aggregate versioning

        $this->state_manager->writeSnapshots();

        foreach ($events as $event) {
            $this->metadata->setLastResults($event, $this->dispatcher->handle($event, $event->states()));
        }

        return $this->commit();
    }

    public function replay(?callable $beforeEach = null, ?callable $afterEach = null)
    {
        $this->is_replaying = true;

        try {
            $this->state_manager->reset(include_storage: true);

            $this->event_store->read()
                ->each(function (Event $event) use ($beforeEach, $afterEach) {
                    $this->state_manager->setReplaying(true);

                    if ($beforeEach) {
                        $beforeEach($event);
                    }

                    $event->states()->each(fn ($state) => $this->dispatcher->apply($event, $state));
                    $this->dispatcher->replay($event, $event->states());

                    if ($afterEach) {
                        $afterEach($event);
                    }

                    return $event;
                });
        } finally {
            $this->state_manager->setReplaying(false);
            $this->is_replaying = false;
        }
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
