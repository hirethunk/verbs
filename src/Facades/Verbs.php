<?php

namespace Thunk\Verbs\Facades;

use Illuminate\Support\Facades\Facade;
use Thunk\Verbs\Lifecycle\Broker;

class Verbs extends Facade
{
    protected static function getFacadeAccessor()
    {
        return Broker::class;
    }
}
