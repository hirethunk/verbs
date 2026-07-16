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
     * Whether any event exists for any of the given states *beyond* that
     * state's own position—i.e. an event the state has not applied yet.
     * Singletons match on type alone, and their events may be recorded under
     * multiple incidental state ids, so their position aggregates across all
     * of them.
     *
     * @param  iterable<int, StateIdentity>  $states
     */
    public function hasEventsBeyondPositions(iterable $states): bool;

    /**
     * Whether any event exists for any of the given states at or below that
     * state's own position but after the given floor—i.e. an event inside
     * the (floor, position] window that the state has already absorbed.
     *
     * @param  iterable<int, StateIdentity>  $states
     */
    public function hasEventsWithinPositions(iterable $states, int|string|null $after = null): bool;

    /**
     * The distinct ids of every event associated with any of the given
     * states, optionally restricted to events after the given position.
     *
     * @param  iterable<int, StateIdentity>  $states
     * @return Collection<int, int|string>
     */
    public function eventIdsForStates(iterable $states, int|string|null $after = null): Collection;

    /**
     * The distinct identities of every state associated with any of the
     * given events.
     *
     * @param  iterable<int, int|string>  $event_ids
     * @return Collection<int, StateIdentity>
     */
    public function statesForEvents(iterable $event_ids): Collection;

    /** @param  Event[]  $events */
    public function write(array $events): bool;
}
