<?php

namespace Thunk\Verbs\Contracts;

use Glhd\Bits\Bits;
use Illuminate\Support\LazyCollection;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Uid\AbstractUid;
use Thunk\Verbs\Event;
use Thunk\Verbs\State;

interface StoresSnapshots
{
    public function load(Bits|UuidInterface|AbstractUid|int|string $id, string $type): ?State;

    public function loadSingleton(string $type): ?State;

    public function write(array $states): bool;

    public function reset(): bool;
}
