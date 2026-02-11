<?php

namespace Thunk\Verbs\Examples\Subscriptions\Events;

use Thunk\Verbs\Attributes\Autodiscovery\AppliesToChildState;
use Thunk\Verbs\Attributes\Autodiscovery\AppliesToState;
use Thunk\Verbs\Event;
use Thunk\Verbs\Examples\Subscriptions\Models\Subscription;
use Thunk\Verbs\Examples\Subscriptions\States\GlobalReportState;
use Thunk\Verbs\Examples\Subscriptions\States\PlanReportState;
use Thunk\Verbs\Examples\Subscriptions\States\SubscriptionState;

#[AppliesToState(state_type: SubscriptionState::class, id: 'subscription_id', alias: 'subscription')]
#[AppliesToChildState(state_type: PlanReportState::class, parent_type: SubscriptionState::class, id: 'plan_id', alias: 'plan')]
#[AppliesToState(state_type: GlobalReportState::class, alias: 'report')]
class SubscriptionCancelled extends Event
{
    public int $subscription_id;

    public function validate(SubscriptionState $state)
    {
        return $state->is_active;
    }

    public function apply(SubscriptionState $state)
    {
        $state->is_active = false;
    }

    public function handle()
    {
        $subscription = Subscription::find($this->subscription_id);

        $subscription->is_active = false;
        $subscription->cancelled_at = now();
        $subscription->save();
    }
}
