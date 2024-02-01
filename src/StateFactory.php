<?php

namespace Thunk\Verbs;

use Thunk\Verbs\Events\VerbsStateInitialized;

class StateFactory
{
    protected ?int $id = null;

    public function __construct(
        protected string $state_class
    ) {
    }

    public function for(int $id)
    {
        $this->id = $id;

        return $this;
    }

    public function create(array $data, ?int $id = null): State
    {
        return VerbsStateInitialized::fire(
            state_id: $id ?? $this->id,
            state_class: $this->state_class,
            state_data: $data
        )->state();
    }
}
