<?php

namespace Thunk\Verbs\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static string id()
 */
class Snowflake extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \Thunk\Verbs\Support\Snowflake::class;
    }
}
