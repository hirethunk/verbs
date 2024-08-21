<?php

namespace Thunk\Verbs\Lifecycle;

use Glhd\Bits\Bits;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Uid\AbstractUid;
use Thunk\Verbs\Contracts\StoresEvents;
use Thunk\Verbs\Event;

class StateReconstructor
{
    public function __construct(
        protected StoresEvents $events,
        protected Dispatcher $dispatcher,
    ) {}

    public function reconstruct(string $type, Bits|UuidInterface|AbstractUid|int|string|null $id)
    {
        $this->events->get($this->events->allRelatedIds($id, $type))
            ->each(fn (Event $event) => $this->dispatcher->apply($event));
    }
}
