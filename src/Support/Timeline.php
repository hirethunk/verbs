<?php

namespace Thunk\Verbs\Support;

use Illuminate\Support\Enumerable;
use Thunk\Verbs\Lifecycle\Lifecycle;
use Thunk\Verbs\Lifecycle\Phases;
use Thunk\Verbs\Lifecycle\StateManager;

class Timeline
{
    public function __construct(
        public StateManager $states,
        public Enumerable $events,
        public Phases $phases,
    ) {}

    public function handle(): static
    {
        $global_registry = app(StateManager::class);

        try {
            app()->instance(StateManager::class, $this->states);

            foreach ($this->events as $event) {
                Lifecycle::run($event, $this->phases);
            }
        } finally {
            app()->instance(StateManager::class, $global_registry);
        }

        return $this;
    }
}
