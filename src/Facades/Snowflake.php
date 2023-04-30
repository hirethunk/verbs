<?php

namespace Thunk\Verbs\Facades;

use Thunk\Verbs\Snowflake as SnowflakeClass;
use Illuminate\Support\Facades\Facade;

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
