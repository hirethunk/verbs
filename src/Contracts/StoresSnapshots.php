<?php

namespace Thunk\Verbs\Contracts;

use Glhd\Bits\Bits;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Uid\AbstractUid;
use Thunk\Verbs\State;
use Thunk\Verbs\Support\StateCollection;

interface StoresSnapshots
{
    public function load(Bits|UuidInterface|AbstractUid|iterable|int|string $id, string $type): State|StateCollection|null;

    public function loadSingleton(string $type): ?State;

    public function write(array $states): bool;

    public function reset(): bool;

    public function delete(Bits|UuidInterface|AbstractUid|int|string ...$ids): bool;
}
