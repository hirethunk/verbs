<?php

namespace Thunk\Verbs\Examples\Subscriptions\States;

use Thunk\Verbs\State;

class SubscriptionState extends State
{
    public int $plan_id;
    public bool $is_active = false;
}
