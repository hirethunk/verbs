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
    ): LazyCollection;

    /** @param  Event[]  $events */
    public function write(array $events): bool;

    /** @param  Event[]  $events */
    public function reattach(array $events): bool;
}
