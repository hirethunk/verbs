<?php

namespace Thunk\Verbs\Lifecycle;

use Glhd\Bits\Bits;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Uid\AbstractUid;
use Thunk\Verbs\Contracts\StoresEvents;
use Thunk\Verbs\Support\StateInstanceCache;

class StateReconstructor
{
    public function __construct(
        protected StoresEvents $events,
    ) {}

    public function reconstruct(string $type, Bits|UuidInterface|AbstractUid|int|string|null $id): StateInstanceCache
    {
        [$state_ids, $event_ids] = $this->events->allRelatedIds($id, $type);

        // TODO
    }
}
