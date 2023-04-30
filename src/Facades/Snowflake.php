<?php

namespace Thunk\Verbs\Facades;

use Illuminate\Support\Facades\Facade;
use Thunk\Verbs\Snowflake as SnowflakeClass;

/**
 * @see \Thunk\Verbs\Snowflake
 */
class Snowflake extends Facade
{
    protected static function getFacadeAccessor()
    {
        return SnowflakeClass::class;
    }
}
