<?php

namespace Thunk\Verbs\Support;

use Illuminate\Support\Enumerable;
use Thunk\Verbs\Contracts\TracksState;
use Thunk\Verbs\Lifecycle\Lifecycle;
use Thunk\Verbs\Lifecycle\Phases;

class Replay
{
    public function __construct(
        public TracksState $states,
        public Enumerable $events,
        public Phases $phases,
    ) {}

    public function handle(): static
    {
        $original_states = app(TracksState::class);

        try {
            app()->instance(TracksState::class, $this->states);

            foreach ($this->events as $event) {
                Lifecycle::run($event, $this->phases);
            }
        } finally {
            app()->instance(TracksState::class, $original_states);
        }

        return $this;
    }
}
