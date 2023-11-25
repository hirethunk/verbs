<?php

namespace Thunk\Verbs\Tests\TestModels;

use Illuminate\Database\Eloquent\Model;
use Thunk\Verbs\Attributes\Autodiscovery\Replayable;

#[Replayable]
class Ticket extends Model
{
    protected $guarded = [];
}
