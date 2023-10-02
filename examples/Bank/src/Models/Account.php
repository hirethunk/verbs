<?php

namespace Thunk\Verbs\Examples\Bank\Models;

use Thunk\Verbs\FromState;
use Glhd\Bits\Database\HasSnowflakes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Thunk\Verbs\Examples\Bank\Events\AccountOpened;

class Account extends Model
{
    use FromState;
    use HasSnowflakes;

    public static function open(int $initial_deposit_in_cents)
    {
        return AccountOpened::fire(
            user_id: Auth::id(),
            initial_deposit_in_cents: $initial_deposit_in_cents,
        );

    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
