<?php

namespace Thunk\Verbs;

use Thunk\Verbs\Events\VerbsStateInitialized;

class StateFactory
{
    protected ?int $id = null;

    protected array $data = [];

    protected $state;

    public function __construct(
        protected string $state_class
    ) {
        $this->state = new $state_class;
    }

    public function for(int $id): static
    {
        $this->id = $id;

        $this->state = $this->state_class::load($id);

        return $this;
    }

    public function create(array $data = [], ?int $id = null): State
    {
        return VerbsStateInitialized::fire(
            state_id: $id ?? $this->id,
            state_class: $this->state_class,
            state_data: array_merge($this->data, $data),
        )->state();
    }
}
