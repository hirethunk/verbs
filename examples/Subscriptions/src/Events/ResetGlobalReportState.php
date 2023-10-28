<?php

namespace Thunk\Verbs\Examples\Subscriptions\Events;

use Thunk\Verbs\Event;
use Thunk\Verbs\Examples\Subscriptions\States\GlobalReportState;

class ResetGlobalReportState extends Event
{
    public function states(): array
    {
        return [
            GlobalReportState::class => GlobalReportState::singleton(),
        ];
    }

    public function apply(GlobalReportState $state)
    {
        $state->subscribes_since_last_report = 0;
        $state->unsubscribes_since_last_report = 0;
        $state->last_reported_at = now();
    }
}
