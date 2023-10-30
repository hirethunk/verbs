<?php

namespace Thunk\Verbs;

use Glhd\Bits\Database\HasSnowflakes;
use Illuminate\Database\Eloquent\Model;

abstract class Projection extends Model
{
    use FromState;
    use HasSnowflakes;

    // TODO: Two traits—one for existing models and one for models that have been event-sourced from the beginning
}
