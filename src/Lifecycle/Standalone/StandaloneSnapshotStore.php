<?php

namespace Thunk\Verbs\Lifecycle\Standalone;

use Glhd\Bits\Bits;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Uid\AbstractUid;
use Thunk\Verbs\Contracts\StoresSnapshots;
use Thunk\Verbs\State;

class StandaloneSnapshotStore implements StoresSnapshots
{
    public $snapshots = [];

    public function load(Bits|UuidInterface|AbstractUid|int|string $id, string $type): ?State
    {
        // @todo - implement
        return null;
    }

    public function loadSingleton(string $type): ?State
    {
        // @todo - implement
        return null;
    }

    public function write(array $states): bool
    {
        // @todo - implement
        return true;
    }

    public function reset(): bool
    {
        $this->snapshots = [];

        return true;
    }
}
