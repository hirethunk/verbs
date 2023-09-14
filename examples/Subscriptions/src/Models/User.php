<?php

namespace Thunk\Verbs\Examples\Subscriptions\Models;

use Glhd\Bits\Database\HasSnowflakes;
use Illuminate\Auth\Authenticatable as AuthenticatableTrait;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Thunk\Verbs\FromState;

class User extends Model implements AuthenticatableContract
{
    use AuthenticatableTrait, FromState, HasFactory, HasSnowflakes;

    protected $guarded = [];

    public function subscribe()
    {
        UserSubscribed::fire($this);
    }

    public function unsubscribe()
    {
        UserUnsubscribed::fire($this);
    }
}
