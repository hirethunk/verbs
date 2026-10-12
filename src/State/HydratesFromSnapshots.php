<?php

namespace Thunk\Verbs\State;

use Glhd\Bits\Bits;
use Illuminate\Support\Collection;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Uid\AbstractUid;
use Thunk\Verbs\Facades\Id;
use Thunk\Verbs\SingletonState;
use Thunk\Verbs\State;

/**
 * Snapshot-backed storage reads, shared by the resolvers that consult storage
 * (Reconstituting and Replay): a missing state loads from its latest snapshot
 * when one exists. Requires a StoresSnapshots $snapshots property on the
 * using class.
 */
trait HydratesFromSnapshots
{
    public function hydrate(string $type, Bits|UuidInterface|AbstractUid|int|string|null $id): ?State
    {
        // Singleton-ness is a property of the *type*, not of whether an id was
        // passed—keying off a null id would route a keyed state loaded with a
        // null key into loadSingleton(). A keyed state with no id returns
        // null, and the manager's make() fails loudly on the missing key.
        if (is_a($type, SingletonState::class, true)) {
            return $this->snapshots->loadSingleton($type);
        }

        return Id::tryFrom($id) === null
            ? null
            : $this->snapshots->load(Id::from($id), $type);
    }

    public function hydrateMany(string $type, Collection $ids): Collection
    {
        if ($ids->isEmpty()) {
            return new Collection;
        }

        // One query for the whole batch rather than one per state.
        return collect($this->snapshots->load($ids->all(), $type));
    }

    public function seedFor(State $state): ?State
    {
        return $this->hydrate($state::class, $state->id);
    }
}
