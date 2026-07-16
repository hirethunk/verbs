<?php

namespace Thunk\Verbs\Contracts;

use Glhd\Bits\Bits;
use Illuminate\Support\Collection;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Uid\AbstractUid;
use Thunk\Verbs\State;
use Thunk\Verbs\State\StateIdentity;
use Thunk\Verbs\Support\StateCollection;

interface StoresSnapshots
{
    public function load(Bits|UuidInterface|AbstractUid|iterable|int|string $id, string $type): State|StateCollection|null;

    public function loadSingleton(string $type): ?State;

    /**
     * The stored last event id of each of the given identities, returned as
     * one identity-with-last_event_id per snapshot that exists. Singletons
     * match on type alone.
     *
     * @param  iterable<int, StateIdentity>  $identities
     * @return Collection<int, StateIdentity>
     */
    public function lastEventIdsFor(iterable $identities): Collection;

    public function write(array $states): bool;

    public function reset(): bool;
}
