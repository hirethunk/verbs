<?php

namespace Thunk\Verbs\Examples\Subscriptions\Models;

use Illuminate\Database\Eloquent\Model;
use Thunk\Verbs\Examples\Subscriptions\Events\SubscriptionCancelled;
use Thunk\Verbs\FromState;

class Subscription extends Model
{
    use FromState;

    public function cancel()
    {
        SubscriptionCancelled::fire($this->id);
    }
}
