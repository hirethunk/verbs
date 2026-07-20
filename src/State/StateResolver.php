<?php

namespace Thunk\Verbs\State;

use Glhd\Bits\Bits;
use Illuminate\Support\Collection;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Uid\AbstractUid;
use Thunk\Verbs\State;

/**
 * The cache-miss/stale-state policy for a StateManager scope. The manager owns
 * the in-memory identity map and applies every result; its resolver answers
 * from storage and policy. "Are we re-applying history?" is a property of the
 * currently bound scope's resolver type (see ReappliesHistory), not an
 * ambient flag.
 *
 * Every method is a pure read except reconcile(), which is deliberately
 * effectful: reconstitution rebuilds a whole connected component and must
 * write its results through the outer scope's identity map (in-place merges,
 * insert-if-absent), which no return value can express without inventing a
 * command object. The drive loop that produces those results is now the shared
 * Replay unit; the write-through half stays here, with the identity map it
 * reconciles.
 */
interface StateResolver
{
    /**
     * Materialize a single state from storage on a cache miss, or null if
     * this policy (or storage) has nothing—the manager then starts it blank.
     */
    public function hydrate(string $type, Bits|UuidInterface|AbstractUid|int|string|null $id): ?State;

    /**
     * Batch form of hydrate() for a many-load. The ids arrive normalized,
     * deduplicated, and known-missing from the cache; only the states found
     * in storage come back, and the manager fills the rest in blank.
     *
     * @param  Collection<int, int|string>  $ids
     * @return Collection<int, State>
     */
    public function hydrateMany(string $type, Collection $ids): Collection;

    /**
     * The storage baseline for re-adopting an instance whose identity the
     * cache lost (e.g. after a replay reset), or null to re-adopt it as-is.
     */
    public function seedFor(State $state): ?State;

    /**
     * Whether this state has in-flight work that storage-derived data must
     * never clobber. The manager checks this before seeding or syncing over
     * an instance; only the reconstituting policy can ever answer true.
     */
    public function hasUncommittedEvents(State $state): bool;

    /**
     * Advance already-resident states that storage has moved past. A no-op
     * for resolvers that re-apply history—there, the replay driver is the
     * only writer, and advancing mid-stream would double-apply events.
     *
     * @param  Collection<int, State>  $states
     */
    public function reconcile(StateManager $memory, Collection $states): void;
}
