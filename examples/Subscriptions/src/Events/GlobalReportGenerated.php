<?php

namespace Thunk\Verbs\Examples\Subscriptions\Events;

use Thunk\Verbs\Event;
use Thunk\Verbs\Examples\Subscriptions\Models\Report;
use Thunk\Verbs\Examples\Subscriptions\States\GlobalReportState;
use Thunk\Verbs\Facades\Verbs;
use Thunk\Verbs\Support\StateCollection;

class GlobalReportGenerated extends Event
{
    public function states(): StateCollection
    {
        return new StateCollection([
            GlobalReportState::class => GlobalReportState::singleton(),
        ]);
    }

    public function once()
    {
        $state = $this->states()[GlobalReportState::class];

        Report::create([
            'plan_id' => null,
            'subscribes_since_last_report' => $state->subscribes_since_last_report,
            'unsubscribes_since_last_report' => $state->unsubscribes_since_last_report,
            'total_subscriptions' => $state->total_subscriptions,
            'summary' => $state->summary(),
        ]);

        Verbs::unlessReplaying(function () {
            ResetGlobalReportState::fire();
        });
    }
}
