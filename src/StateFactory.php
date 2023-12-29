<?php

namespace Thunk\Verbs;

use Glhd\Bits\Bits;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Uid\AbstractUid;
use Thunk\Verbs\Events\VerbsStateInitialized;
use Thunk\Verbs\Lifecycle\EventStore;
use Thunk\Verbs\Lifecycle\StateManager;
use Thunk\Verbs\Support\StateSerializer;

class StateFactory
{
    public function __construct(
        protected string $state_class
    ){
    }

    public function create(array $data, ?int $id = null): State
    {
        return VerbsStateInitialized::fire(
            state_id: $id,
            state_class: $this->state_class,
            state_data: $data
        )->state();
    }
}
