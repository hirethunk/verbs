<?php

namespace Thunk\Verbs\State;

use Glhd\Bits\Bits;
use Illuminate\Support\Collection;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Uid\AbstractUid;
use Thunk\Verbs\State;

/**
 * The cache-miss/stale-state policy for a StateManager scope. The manager owns
 * only the in-memory identity map; its resolver owns all storage access and
 * decides how a missing state materializes and whether a stale one advances.
 * "Are we re-applying history?" is a property of the currently bound scope's
 * resolver type (see ReappliesHistory), not an ambient flag.
 */
interface StateResolver
{
    /**
     * Materialize a single state on a cache miss, leaving it resident in
     * $memory's cache.
     */
    public function resolve(StateManager $memory, string $type, Bits|UuidInterface|AbstractUid|int|string|null $id): State;

    /**
     * Batch-hydrate whichever of the given ids aren't already resident in
     * $memory's cache. Returns nothing: the caller re-reads the cache, so the
     * hydration mechanics (snapshot maps, blank fills) stay in here.
     */
    public function resolveMany(StateManager $memory, string $type, iterable $ids): void;

    /**
     * Advance already-resident states that storage has moved past. A no-op for
     * resolvers that re-apply history—there, the replay driver is the only
     * writer, and advancing mid-stream would double-apply events.
     *
     * @param  Collection<int, State>  $states
     */
    public function reconcile(StateManager $memory, Collection $states): void;

    /**
     * Seed a re-adopted instance from durable storage when the cache lost its
     * identity (e.g. after a replay reset), before it's re-registered as
     * canonical.
     */
    public function reseed(StateManager $memory, State $state): void;

    /**
     * Bring a caller-held instance in line with the canonical instance that
     * now owns its identity, when the two have diverged.
     */
    public function sync(StateManager $memory, State $canonical, State $into): void;
}
