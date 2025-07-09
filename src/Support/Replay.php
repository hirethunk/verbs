<?php

namespace Thunk\Verbs\Support;

use Illuminate\Support\Enumerable;
use Thunk\Verbs\Contracts\TracksState;
use Thunk\Verbs\Lifecycle\Lifecycle;
use Thunk\Verbs\Lifecycle\Phases;
use Thunk\Verbs\State\TemporaryStateManager;

class Replay
{
    public function __construct(
        public Enumerable $events,
        public Phases $phases,
        public TracksState $states = new TemporaryStateManager,
    ) {}

    public function handle(): static
    {
        $original_states = app(TracksState::class);

        try {
            app()->instance(TracksState::class, $this->states);

            foreach ($this->events as $event) {
                Lifecycle::run($event, $this->phases);
            }

            // FIXME: This will throw an exception right now
            // foreach ($this->states as $state) {
            //     $original_states->register($state);
            // }
        } finally {
            app()->instance(TracksState::class, $original_states);
        }

        return $this;
    }
}
