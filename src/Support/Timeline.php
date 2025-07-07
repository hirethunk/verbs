<?php

namespace Thunk\Verbs\Support;

use Illuminate\Support\Enumerable;
use Thunk\Verbs\Lifecycle\Phases;

class Timeline
{
    public function __construct(
        public StateInstanceCache $states, // This should be a new thing that replaces the StateManager, "StateRegistry"
        public Enumerable $events,
    ) {}

    public function handle(Phases $phases): static
    {
        // Loop over events, replay configured hooks, apply snapshots as needed
        // Use the Dispatcher to call the appropriate hooks

        // Load GameState + fire event
        //  - apply to GameState

        return $this;
    }
}
