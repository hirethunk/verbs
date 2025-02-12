<?php

namespace Thunk\Verbs\Lifecycle;

use Glhd\Bits\Bits;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Uid\AbstractUid;
use Thunk\Verbs\Contracts\StoresSnapshots;
use Thunk\Verbs\State;
use Thunk\Verbs\Support\StateCollection;

class NullSnapshotStore implements StoresSnapshots
{
    public function load(Bits|UuidInterface|AbstractUid|iterable|int|string $id, string $type): State|StateCollection|null
    {
        return null;
    }

    public function loadSingleton(string $type): ?State
    {
        return null;
    }

    public function write(array $states): bool
    {
        return true;
    }

    public function delete(Bits|UuidInterface|AbstractUid|int|string ...$ids): bool
    {
        return true;
    }

    public function reset(): bool
    {
        return true;
    }
}
