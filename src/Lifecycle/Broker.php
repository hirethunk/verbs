<?php

namespace Thunk\Verbs\Lifecycle;

use Illuminate\Contracts\Auth\Guard;
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

        $states->each(fn ($state) => Guards::for($event, $state)->check());

        Guards::for($event, null)->check();

        $states->each(fn ($state) => $this->dispatcher->apply($event, $state));

        app(Queue::class)->queue($event);

        $this->dispatcher->fired($event, $states);

        if ($this->commit_immediately || $event instanceof CommitsImmediately) {
            $this->commit();
        }

        return $event;
    }

    public function commit(): bool
    {
        $events = app(EventQueue::class)->flush();

        if (empty($events)) {
            return true;
        }

        // FIXME: Only write changes + handle aggregate versioning

        app(StateManager::class)->writeSnapshots();

        foreach ($events as $event) {
            $this->metadata->setLastResults($event, $this->dispatcher->handle($event, $event->states()));
        }

        return $this->commit();
    }

    public function replay(?callable $beforeEach = null, ?callable $afterEach = null, bool $skipInvalid = false): void
    {
        $this->is_replaying = true;

        $state_manager = app(StateManager::class);

        try {
            $state_manager->reset(include_storage: true);

            app(StoresEvents::class)->read()
                ->each(function (Event $event) use ($state_manager, $beforeEach, $afterEach, $skipInvalid) {
                    $state_manager->setReplaying(true);

                    if ($beforeEach) {
                        $beforeEach($event);
                    }

                    if ($skipInvalid && ! $this->isValid($event)) {
                        return;
                    }

                    $event->states()->each(fn ($state) => $this->dispatcher->apply($event, $state));
                    $this->dispatcher->replay($event, $event->states());

                    if ($afterEach) {
                        $afterEach($event);
                    }

                    $state_manager->prune();

                    return $event;
                });
        } finally {
            $state_manager->setReplaying(false);
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
