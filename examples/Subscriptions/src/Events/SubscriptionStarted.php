<?php

namespace Thunk\Verbs\Examples\Subscriptions\Events;

use Thunk\Verbs\Event;
use Thunk\Verbs\Examples\Subscriptions\Models\Subscription;
use Thunk\Verbs\Examples\Subscriptions\States\GlobalReportState;
use Thunk\Verbs\Examples\Subscriptions\States\PlanReportState;
use Thunk\Verbs\Examples\Subscriptions\States\SubscriptionState;

class SubscriptionStarted extends Event
{
    public SubscriptionState $subscription_state;

    public GlobalReportState $global_report_state;

    public PlanReportState $plan_report_state;

    public function __construct(
        public int $user_id,
        public int $plan_id,
    ) {
        $this->subscription_state = SubscriptionState::initialize();
        $this->plan_report_state = PlanReportState::load($plan_id);
        $this->global_report_state = GlobalReportState::singleton();
    }

    public function validate(SubscriptionState $state)
    {
        return ! $state->is_active;
    }

    public function apply(SubscriptionState $state)
    {
        $state->is_active = true;
        $state->plan_id = $this->plan_id;
    }

    public function onFire()
    {
        Subscription::create([
            'id' => $this->subscription_state->id,
            'user_id' => $this->user_id,
        ]);
    }
}
