<?php

namespace Thunk\Verbs\Lifecycle;

use Carbon\CarbonInterface;
use Thunk\Verbs\CommitsImmediately;
use Thunk\Verbs\Contracts\BrokersEvents;
use Thunk\Verbs\Contracts\StoresEvents;
use Thunk\Verbs\Event;
use Thunk\Verbs\Lifecycle\Queue as EventQueue;
use Thunk\Verbs\Support\Wormhole;

class Broker implements BrokersEvents
{
    use BrokerConvenienceMethods;

    public bool $commit_immediately = false;

    public function __construct(
        protected Dispatcher $dispatcher,
        protected MetadataManager $metadata,
        protected Wormhole $wormhole,
    ) {
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

        $this->dispatcher->fired($event, $states);

        app(Queue::class)->queue($event);

        if ($this->commit_immediately || $event instanceof CommitsImmediately) {
            $this->commit();
        }

        return $event;
    }

    public function commit(): bool
    {
        $events = app(EventQueue::class)->flush();

        // FIXME: Only write changes + handle aggregate versioning

        app(StateManager::class)->writeSnapshots();

        if (empty($events)) {
            return true;
        }

        foreach ($events as $event) {
            $this->metadata->setLastResults($event, $this->dispatcher->handle($event));
        }

        return $this->commit();
    }

    public function replay(?callable $beforeEach = null, ?callable $afterEach = null)
    {
        $this->is_replaying = true;

        app(SnapshotStore::class)->reset();

        app(StoresEvents::class)->read()
            ->each(function (Event $event) use ($beforeEach, $afterEach) {
                app(StateManager::class)->setMaxEventId($event->id);

                if ($beforeEach) {
                    $beforeEach($event);
                }

                $event->states()
                    ->each(fn ($state) => $this->dispatcher->apply($event, $state))
                    ->each(fn ($state) => $this->dispatcher->replay($event, $state))
                    ->whenEmpty(fn () => $this->dispatcher->replay($event, null));

                if ($afterEach) {
                    $afterEach($event);
                }

                return $event;
            });

        $this->is_replaying = false;
    }

    public function commitImmediately(bool $commit_immediately = true): void
    {
        $this->commit_immediately = $commit_immediately;
    }

    public function realNow(): CarbonInterface
    {
        return $this->wormhole->realNow();
    }
}
