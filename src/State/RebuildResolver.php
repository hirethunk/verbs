<?php

namespace Thunk\Verbs\State;

use Glhd\Bits\Bits;
use Illuminate\Support\Collection;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Uid\AbstractUid;
use Thunk\Verbs\State;

/**
 * hydrate-on-miss: blank · advance-on-stale: no
 *
 * The scope *is* the replay: every state starts blank and advances only by
 * the events the driver feeds in. This resolver must stay dependency-free—
 * the isolated reconstitution sub-scope is built with no container, Broker,
 * or store involvement, which is what keeps the old Broker↔StateManager
 * circular dependency from ever coming back.
 */
class RebuildResolver implements ReappliesHistory, StateResolver
{
    public function hydrate(string $type, Bits|UuidInterface|AbstractUid|int|string|null $id): ?State
    {
        return null;
    }

    public function hydrateMany(string $type, Collection $ids): Collection
    {
        return new Collection;
    }

    public function seedFor(State $state): ?State
    {
        return null;
    }

    public function hasUncommittedEvents(State $state): bool
    {
        return false;
    }

    public function reconcile(StateManager $memory, Collection $states): void
    {
        //
    }
}
