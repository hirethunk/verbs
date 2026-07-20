<?php

namespace Thunk\Verbs\State;

use Glhd\Bits\Bits;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Uid\AbstractUid;
use Thunk\Verbs\Facades\Id;
use Thunk\Verbs\SingletonState;
use Thunk\Verbs\State;

/**
 * Snapshot-backed miss hydration, shared by the resolvers that consult
 * storage (Reconstituting and Replay): a missing state loads from its latest
 * snapshot when one exists and starts blank otherwise. Requires a
 * StoresSnapshots $snapshots property on the using class.
 */
trait HydratesFromSnapshots
{
    public function resolve(StateManager $memory, string $type, Bits|UuidInterface|AbstractUid|int|string|null $id): State
    {
        // Singleton-ness is a property of the *type*, not of whether an id was
        // passed—keying off a null id would route a keyed state loaded with a
        // null key into loadSingleton(). A keyed state with no id falls through
        // to make(), which fails loudly on the missing key.
        if (is_a($type, SingletonState::class, true)) {
            $snapshot = $this->snapshots->loadSingleton($type);
        } else {
            $snapshot = Id::tryFrom($id) === null
                ? null
                : $this->snapshots->load(Id::from($id), $type);
        }

        return $snapshot instanceof State
            ? $memory->cache->put($snapshot)
            : $memory->make($type, $id);
    }

    public function resolveMany(StateManager $memory, string $type, iterable $ids): void
    {
        // Singletons never take the batch path—their snapshot is keyed by
        // type, not id.
        if (is_a($type, SingletonState::class, true)) {
            foreach ($ids as $id) {
                if (! $memory->cache->has($type, $memory->cacheId($type, $id))) {
                    $this->resolve($memory, $type, $id);
                }
            }

            return;
        }

        $missing = collect($ids)
            ->map(fn ($id) => Id::tryFrom($id))
            ->filter(fn ($id) => $id !== null && ! $memory->cache->has($type, $id))
            ->unique()
            ->values();

        if ($missing->isEmpty()) {
            return;
        }

        // Hydrate every missing snapshot in one query rather than one per state.
        $snapshots = collect($this->snapshots->load($missing->all(), $type))
            ->keyBy(fn (State $state) => (string) $state->id);

        foreach ($missing as $id) {
            if ($snapshot = $snapshots->get((string) $id)) {
                $memory->cache->put($snapshot);
            } else {
                $memory->make($type, $id);
            }
        }
    }

    protected function latestSnapshotFor(State $state): ?State
    {
        if ($state instanceof SingletonState) {
            return $this->snapshots->loadSingleton($state::class);
        }

        return Id::tryFrom($state->id) === null
            ? null
            : $this->snapshots->load(Id::from($state->id), $state::class);
    }
}
