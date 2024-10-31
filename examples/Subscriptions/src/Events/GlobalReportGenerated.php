<?php

namespace Thunk\Verbs\Examples\Subscriptions\Events;

use Thunk\Verbs\Attributes\Autodiscovery\AppliesToState;
use Thunk\Verbs\Attributes\Hooks\Once;
use Thunk\Verbs\Event;
use Thunk\Verbs\Examples\Subscriptions\Models\Report;
use Thunk\Verbs\Examples\Subscriptions\States\GlobalReportState;

#[AppliesToState(GlobalReportState::class)]
class GlobalReportGenerated extends Event
{
    #[Once]
    public function handle()
    {
        $state = $this->state(GlobalReportState::class);

        Report::create([
            'plan_id' => null,
            'subscribes_since_last_report' => $state->subscribes_since_last_report,
            'unsubscribes_since_last_report' => $state->unsubscribes_since_last_report,
            'total_subscriptions' => $state->total_subscriptions,
            'summary' => $state->summary(),
        ]);

        ResetGlobalReportState::fire();
    }
}
