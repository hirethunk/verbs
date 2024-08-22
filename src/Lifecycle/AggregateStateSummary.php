<?php

namespace Thunk\Verbs\Lifecycle;

use Illuminate\Support\Collection;
use Thunk\Verbs\State;

class AggregateStateSummary
{
    public function __construct(
        public readonly State $state,
        public readonly Collection $related_event_ids,
        public readonly Collection $related_state_ids,
        public readonly ?int $min_applied_event_id,
        public readonly ?int $max_applied_event_id,
    ) {}
}
