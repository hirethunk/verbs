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

    /** @var array<string, int> */
    protected array $queued_state_keys = [];

    public function queue(Event $event)
    {
        $this->event_queue[] = $event;

        $this->index($event);
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
        $this->queued_state_keys = [];

        return $events;
    }

    public function getEvents(): array
    {
        return $this->event_queue;
    }

    public function hasEventsFor(State $state): bool
    {
        return isset($this->queued_state_keys[$this->stateKey($state)]);
    }

    /** @param  Event[]  $events */
    public function restore(array $events): void
    {
        $this->event_queue = array_merge($events, $this->event_queue);

        foreach ($events as $event) {
            $this->index($event);
        }
    }

    /**
     * Membership is asked once per live state on every reconstitution, so the
     * queue keeps a refcounted key index instead of re-scanning every queued
     * event's states each time. states() is already resolved (and memoized)
     * by the time an event queues, so the keys are stable.
     */
    protected function index(Event $event): void
    {
        foreach ($event->states() as $state) {
            $key = $this->stateKey($state);

            $this->queued_state_keys[$key] = ($this->queued_state_keys[$key] ?? 0) + 1;
        }
    }

    /**
     * Singletons key by type alone (their in-memory ids are incidental), and
     * ids normalize to strings so int/string driver drift can't miss—mirroring
     * how the state cache keys identities.
     */
    protected function stateKey(State $state): string
    {
        return $state instanceof SingletonState
            ? $state::class
            : $state::class.':'.Id::tryFrom($state->id);
    }
}
