<?php

namespace Thunk\Verbs\Examples\Subscriptions\States;

use Illuminate\Support\Carbon;
use Thunk\Verbs\Examples\Subscriptions\Events\SubscriptionCancelled;
use Thunk\Verbs\Examples\Subscriptions\Events\SubscriptionStarted;
use Thunk\Verbs\State;

class PlanReportState extends State
{
    public int $total_subscriptions = 0;

    public int $subscribes_since_last_report = 0;

    public int $unsubscribes_since_last_report = 0;

    public Carbon $last_reported_at;

    public function summary(): string
    {
        $churn = $this->subscribes_since_last_report > 0
            ? ($this->unsubscribes_since_last_report / $this->subscribes_since_last_report) * 100
            : 0;

        return implode('; ', [
            "{$this->subscribes_since_last_report} subscribe(s)",
            "{$this->unsubscribes_since_last_report} unsubscribe(s)",
            "{$churn}% churn",
        ]);
    }

    public function applySubscriptionStarted(SubscriptionStarted $e)
    {
        $this->total_subscriptions++;
        $this->subscribes_since_last_report++;
    }

    public function applySubscriptionCancelled(SubscriptionCancelled $e)
    {
        $this->unsubscribes_since_last_report++;
        $this->total_subscriptions--;
    }
}
