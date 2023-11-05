<?php

namespace Thunk\Verbs\Examples\Subscriptions\Events;

use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;
use Thunk\Verbs\Examples\Subscriptions\Models\Report;
use Thunk\Verbs\Examples\Subscriptions\States\PlanReportState;
use Thunk\Verbs\Facades\Verbs;

class PlanReportGenerated extends Event
{
    #[StateId(PlanReportState::class)]
    public int $plan_id;

    public function once()
    {
        $state = $this->state(PlanReportState::class);

        Report::create([
            'plan_id' => $this->plan_id,
            'subscribes_since_last_report' => $state->subscribes_since_last_report,
            'unsubscribes_since_last_report' => $state->unsubscribes_since_last_report,
            'total_subscriptions' => $state->total_subscriptions,
            'summary' => $state->summary(),
        ]);

        Verbs::unlessReplaying(function () {
            ResetPlanReportState::fire(plan_id: $this->plan_id);
        });
    }
}
