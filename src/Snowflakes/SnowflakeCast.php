<?php

namespace Thunk\Verbs\Snowflakes;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Thunk\Verbs\Facades\Snowflake as SnowflakeFacade;

class SnowflakeCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes)
    {
        if (!($value instanceof Snowflake)) {
            $value = SnowflakeFacade::fromId($value);
        }

        return $value;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes)
    {
        if ($value instanceof Snowflake) {
            $value = $value->id();
        }

        return (int) $value;
    }
}
