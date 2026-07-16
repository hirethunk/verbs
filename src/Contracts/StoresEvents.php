<?php

namespace Thunk\Verbs\Contracts;

use Glhd\Bits\Bits;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Uid\AbstractUid;
use Thunk\Verbs\Event;
use Thunk\Verbs\State;
use Thunk\Verbs\State\StateIdentity;

interface StoresEvents
{
    public function read(
        ?State $state = null,
        Bits|UuidInterface|AbstractUid|int|string|null $after_id = null,
    ): LazyCollection;

    /**
     * Stream the events with the given ids, in id order. The list may be
     * arbitrarily large, so implementations must chunk their own reads.
     *
     * @param  iterable<int, Bits|UuidInterface|AbstractUid|int|string>  $ids
     * @return LazyCollection<int, Event>
     */
    public function get(iterable $ids): LazyCollection;

    /**
     * Whether any event exists for any of the given identities *beyond* that
     * identity's own `last_event_id`—i.e. an event the state has not applied
     * yet. Singletons match on type alone, and their events may be recorded
     * under multiple incidental state ids, so their last event id aggregates
     * across all of them.
     *
     * @param  iterable<int, StateIdentity>  $identities
     */
    public function hasUnappliedEvents(iterable $identities): bool;

    /**
     * Whether any event exists for any of the given identities at or below
     * that identity's own `last_event_id` but after `$after_id`—i.e. an
     * already-applied event inside the (after_id, last_event_id] window.
     *
     * @param  iterable<int, StateIdentity>  $identities
     */
    public function hasAppliedEventsAfter(iterable $identities, int|string|null $after_id = null): bool;

    /**
     * The distinct ids of every event associated with any of the given
     * identities, optionally restricted to events after `$after_id`.
     *
     * @param  iterable<int, StateIdentity>  $identities
     * @return Collection<int, int|string>
     */
    public function eventIdsFor(iterable $identities, int|string|null $after_id = null): Collection;

    /**
     * The distinct identities of every state associated with any of the
     * given events.
     *
     * @param  iterable<int, int|string>  $event_ids
     * @return Collection<int, StateIdentity>
     */
    public function stateIdentitiesFor(iterable $event_ids): Collection;

    /** @param  Event[]  $events */
    public function write(array $events): bool;
}
