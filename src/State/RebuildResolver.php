<?php

namespace Thunk\Verbs\State;

use Closure;
use Glhd\Bits\Bits;
use Illuminate\Support\Collection;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Uid\AbstractUid;
use Thunk\Verbs\State;

/**
 * hydrate-on-miss: blank (or the given fallback) · advance-on-stale: no
 *
 * The scope *is* the replay: every state starts blank and advances only by
 * the events the driver feeds in. This resolver must stay dependency-free—
 * the isolated reconstitution sub-scope is built with no container, Broker,
 * or store involvement, which is what keeps the old Broker↔StateManager
 * circular dependency from ever coming back.
 *
 * A caller may pass a fallback for misses that shouldn't start blank (e.g.
 * states outside a reconstitution's component); returning null from it means
 * "start blank."
 */
class RebuildResolver implements ReappliesHistory, StateResolver
{
    public function __construct(
        protected ?Closure $fallback = null,
    ) {}

    public function hydrate(string $type, Bits|UuidInterface|AbstractUid|int|string|null $id): ?State
    {
        return $this->fallback ? call_user_func($this->fallback, $type, $id) : null;
    }

    public function hydrateMany(string $type, Collection $ids): Collection
    {
        return $ids
            ->map(fn ($id) => $this->hydrate($type, $id))
            ->filter()
            ->values();
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
