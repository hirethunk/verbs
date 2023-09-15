<?php

namespace Thunk\Verbs\Examples\Subscriptions\Events;

use Thunk\Verbs\Event;
use Thunk\Verbs\Examples\Subscriptions\Models\Subscription;
use Thunk\Verbs\Examples\Subscriptions\States\GlobalReportState;
use Thunk\Verbs\Examples\Subscriptions\States\PlanReportState;
use Thunk\Verbs\Examples\Subscriptions\States\SubscriptionState;

class SubscriptionCancelled extends Event
{
    public int $subscription_id;

    public function states(): array
    {
        return [
            $subscription_state = SubscriptionState::load($this->subscription_id),
            PlanReportState::load($subscription_state->plan_id),
            GlobalReportState::singleton(),
        ];
    }

    public function validate(SubscriptionState $state)
    {
        return $state->is_active;
    }

    public function apply(SubscriptionState $state)
    {
        $state->is_active = false;
    }

    public function onFire()
    {
        $subscription = Subscription::find($this->subscription_id);

        $subscription->is_active = false;
        $subscription->cancelled_at = now();
        $subscription->save();
    }
}
