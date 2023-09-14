<?php

namespace Thunk\Verbs\Examples\Subscriptions\Events;

use Thunk\Verbs\Event;
use Thunk\Verbs\Examples\Subscriptions\States\PlanReportState;

class PlanReportGenerated extends Event
{
    public PlanReportState $state;

    public function __construct(
        public int $plan_id,
    ) {
        $this->state = PlanReportState::load($this->plan_id);
    }

    public function apply(PlanReportState $state)
    {
        $state->subscribes_since_last_report = 0;
        $state->unsubscribes_since_last_report = 0;
        $state->last_reported_at = now();
    }

    public function onCommit()
    {
        // email the report
    }
}
