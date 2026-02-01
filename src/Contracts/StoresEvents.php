<?php

namespace Thunk\Verbs\Contracts;

use Glhd\Bits\Bits;
use Illuminate\Support\LazyCollection;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Uid\AbstractUid;
use Thunk\Verbs\Event;
use Thunk\Verbs\Lifecycle\AggregateStateSummary;
use Thunk\Verbs\State;

interface StoresEvents
{
    public function read(
        ?State $state = null,
        Bits|UuidInterface|AbstractUid|int|string|null $after_id = null,
    ): LazyCollection;

    public function get(iterable $ids): LazyCollection;

    /** @param  Event[]  $events */
    public function write(array $events): bool;

    public function summarize(State ...$states): AggregateStateSummary;
}
