<?php

namespace Thunk\Verbs\Examples\Subscriptions\Models;

use Glhd\Bits\Database\HasSnowflakes;
use Illuminate\Auth\Authenticatable as AuthenticatableTrait;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Thunk\Verbs\Examples\Subscriptions\Events\SubscriptionStarted;
use Thunk\Verbs\FromState;

class User extends Model implements AuthenticatableContract
{
    use AuthenticatableTrait, FromState, HasFactory, HasSnowflakes;

    protected $guarded = [];

    public function subscribe(Plan $plan)
    {
        SubscriptionStarted::fire(
            user_id: $this->id,
            plan_id: $plan->id
        );
    }

    public function activeSubscription(Plan $plan): ?Subscription
    {
        return $this->active_subscriptions()->firstWhere([
            'plan_id' => $plan->id,
        ]);
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function active_subscriptions()
    {
        return $this->subscriptions()->where('is_active', true);
    }
}
