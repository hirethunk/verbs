<?php

namespace Thunk\Verbs\Contracts;

use Glhd\Bits\Bits;
use Illuminate\Support\Collection;
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
        bool $singleton = false
    ): LazyCollection;

    public function get(iterable $ids): LazyCollection;

    /** @param  Event[]  $events */
    public function write(array $events): bool;

    public function allRelatedIds(Bits|UuidInterface|AbstractUid|int|string|null $state_id, ?string $type): Collection;
}
