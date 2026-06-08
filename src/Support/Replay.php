<?php

namespace Thunk\Verbs\Support;

use Illuminate\Support\Enumerable;
use Thunk\Verbs\Lifecycle\Lifecycle;
use Thunk\Verbs\Lifecycle\Phases;
use Thunk\Verbs\State\Scope;

class Replay
{
    public function __construct(
        public Scope $states,
        public Enumerable $events,
        public Phases $phases,
    ) {}

    public function handle(): static
    {
        $this->states->run(function () {
            foreach ($this->events as $event) {
                Lifecycle::run($event, $this->phases);
            }
        });

        return $this;
    }
}
