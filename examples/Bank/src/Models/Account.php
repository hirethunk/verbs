<?php

namespace Thunk\Verbs\Examples\Bank\Models;

use Glhd\Bits\Database\HasSnowflakes;
use Illuminate\Database\Eloquent\Model;
use Thunk\Verbs\FromState;

class Account extends Model
{
    use HasSnowflakes;
    use FromState;

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
