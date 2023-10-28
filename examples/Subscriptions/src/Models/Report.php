<?php

namespace Thunk\Verbs\Examples\Subscriptions\Models;

use Glhd\Bits\Database\HasSnowflakes;
use Illuminate\Database\Eloquent\Model;
use Thunk\Verbs\FromState;

class Report extends Model
{
    use FromState;
    use HasSnowflakes;
}
