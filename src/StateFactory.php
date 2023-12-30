<?php

namespace Thunk\Verbs;

use Thunk\Verbs\Events\VerbsStateInitialized;

class StateFactory
{
    public function __construct(
        protected string $state_class
    ) {
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
