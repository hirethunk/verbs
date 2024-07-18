<?php

namespace Thunk\Verbs\Events;

use Thunk\Verbs\CommitsImmediately;
use Thunk\Verbs\Event;
use Thunk\Verbs\Support\StateCollection;

/** @template TStateType */
class VerbsStateInitialized extends Event implements CommitsImmediately
{
    /** @param  class-string<TStateType>  $state_class */
    public function __construct(
        public int|string $state_id,
        public string $state_class,
        public array $state_data,
        public bool $singleton = false,
    ) {}

    /** @return StateCollection<int, TStateType> */
    public function states(): StateCollection
    {
        return StateCollection::make([
            $this->singleton ? $this->state_class::singleton() : $this->state_class::load($this->state_id),
        ]);
    }

    public function validate()
    {
        $this->assert(
            $this->state()->last_event_id === null,
            'State has already been initialized',
        );
    }

    public function apply()
    {
        $state = $this->state($this->state_class);

        foreach ($this->state_data as $key => $value) {
            $state->$key = $value;
        }
    }
}
