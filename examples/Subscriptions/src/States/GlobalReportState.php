<?php

namespace Thunk\Verbs\Examples\Subscriptions\States;

use Illuminate\Support\Carbon;
use Thunk\Verbs\State;

class GlobalReportState extends State
{
    public int $total_subscriptions = 0;
    public int $subscribes_since_last_report = 0;
    public int $unsubscribes_since_last_report = 0;

    public Carbon $last_reported_at;

    public function applySubscriptionStarted(SubscriptionStarted $e)
    {
        $this->total_subscriptions++;
        $this->subscribes_since_last_report++;
    }

    public function applySubscriptionCancelled(SubscriptionCancelled $e)
    {
        $this->total_subscriptions--;
        $this->unsubscribes_since_last_report++;
    }
}
