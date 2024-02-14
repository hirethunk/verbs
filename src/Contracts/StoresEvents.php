<?php

namespace Thunk\Verbs\Contracts;

use Glhd\Bits\Bits;
use Illuminate\Support\LazyCollection;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Uid\AbstractUid;
use Thunk\Verbs\Event;
use Thunk\Verbs\State;

interface StoresEvents
{
    public function read(
        ?State $state = null,
        Bits|UuidInterface|AbstractUid|int|string|null $after_id = null,
        Bits|UuidInterface|AbstractUid|int|string|null $up_to_id = null,
        bool $singleton = false
    ): LazyCollection;

    /** @param  Event[]  $events */
    public function write(array $events): bool;
}
