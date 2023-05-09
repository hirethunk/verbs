<?php

namespace Thunk\Verbs\Facades;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Facade;
use Thunk\Verbs\Snowflakes\Factory;

/**
 * @method static \Thunk\Verbs\Snowflakes\Snowflake make()
 * @method static \Thunk\Verbs\Snowflakes\Snowflake makeFromTimestampForQuery(CarbonInterface $timestamp)
 * @method static \Thunk\Verbs\Snowflakes\Snowflake fromId(int|string $id)
 * @method static \Thunk\Verbs\Snowflakes\Snowflake coerce(int|string|\Thunk\Verbs\Snowflakes\Snowflake $id)
 */
class Snowflake extends Facade
{
    protected static function getFacadeAccessor()
    {
        return Factory::class;
    }
}
