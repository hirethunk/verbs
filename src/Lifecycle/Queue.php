<?php

namespace Thunk\Verbs\Lifecycle;

use Thunk\Verbs\Contracts\StoresEvents;
use Thunk\Verbs\Event;
use Thunk\Verbs\Exceptions\UnableToStoreEventsException;
use Thunk\Verbs\Facades\Id;
use Thunk\Verbs\SingletonState;
use Thunk\Verbs\State;

class Queue
{
    public array $event_queue = [];

    public function queue(Event $event)
    {
        $this->event_queue[] = $event;
    }

    public function flush(): array
    {
        $events = $this->event_queue;

        // Concurrency is guarded at the storage layer: EventStore::write() runs
        // guardAgainstConcurrentWrites() against the persisted max event id per
        // state before inserting, throwing a ConcurrencyException on a conflict.
        if (! app(StoresEvents::class)->write($events)) {
            throw new UnableToStoreEventsException($events);
        }

        $this->event_queue = [];

        return $events;
    }

    public function getEvents(): array
    {
        return $this->event_queue;
    }

    public function hasEventsFor(State $state): bool
    {
        foreach ($this->event_queue as $event) {
            $touched = $event->states()->contains(function (State $queued) use ($state) {
                if ($queued::class !== $state::class) {
                    return false;
                }

                // Singletons match on type alone (their in-memory ids are
                // incidental), and ids compare in normalized string form to
                // mirror how the state cache keys identities.
                return $state instanceof SingletonState
                    || (string) Id::tryFrom($queued->id) === (string) Id::tryFrom($state->id);
            });

            if ($touched) {
                return true;
            }
        }

        return false;
    }

    /** @param  Event[]  $events */
    public function restore(array $events): void
    {
        $this->event_queue = array_merge($events, $this->event_queue);
    }
}
