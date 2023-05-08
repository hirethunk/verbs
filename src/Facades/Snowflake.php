<?php

namespace Thunk\Verbs\Facades;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Facade;
use Thunk\Verbs\Support\SnowflakeFactory;

/**
 * @method static \Thunk\Verbs\Support\Snowflake make()
 * @method static \Thunk\Verbs\Support\Snowflake makeFromTimestampForQuery(CarbonInterface $timestamp)
 * @method static \Thunk\Verbs\Support\Snowflake fromId(int|string $id)
 */
class Snowflake extends Facade
{
    protected static function getFacadeAccessor()
    {
        return SnowflakeFactory::class;
    }
}
