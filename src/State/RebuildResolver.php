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
    public function resolve(StateManager $memory, string $type, Bits|UuidInterface|AbstractUid|int|string|null $id): State
    {
        return $memory->make($type, $id);
    }

    public function resolveMany(StateManager $memory, string $type, iterable $ids): void
    {
        foreach ($ids as $id) {
            $memory->make($type, $id);
        }
    }

    public function reconcile(StateManager $memory, Collection $states): void
    {
        //
    }

    public function reseed(StateManager $memory, State $state): void
    {
        //
    }

    public function sync(StateManager $memory, State $canonical, State $into): void
    {
        $memory->merge($canonical, $into);
    }
}
