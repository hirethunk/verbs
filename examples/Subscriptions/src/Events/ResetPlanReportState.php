<?php

namespace Thunk\Verbs\Examples\Subscriptions\Events;

use Thunk\Verbs\Event;
use Thunk\Verbs\Examples\Subscriptions\States\PlanReportState;
use Thunk\Verbs\Support\StateCollection;

class ResetPlanReportState extends Event
{
    public int $plan_id;

    public function states(): StateCollection
    {
        return new StateCollection([
            PlanReportState::class => PlanReportState::load($this->plan_id),
        ]);
    }

    public function apply(PlanReportState $state)
    {
        $state->subscribes_since_last_report = 0;
        $state->unsubscribes_since_last_report = 0;
        $state->last_reported_at = now();
    }
}
