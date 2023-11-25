<?php

namespace Thunk\Verbs\Examples\Bank\Models;

use Glhd\Bits\Database\HasSnowflakes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Thunk\Verbs\Attributes\Autodiscovery\Replayable;
use Thunk\Verbs\Examples\Bank\Events\AccountOpened;

#[Replayable(truncate: true)]
class Account extends Model
{
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
