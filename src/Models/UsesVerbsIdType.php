<?php

namespace Thunk\Verbs\Models;

use UnexpectedValueException;

/**
 * To support custom IDs, we need to override the default Eloquent behavior.
 */
trait UsesVerbsIdType
{
    public function getKeyType()
    {
        $id_type = strtolower(config('verbs.id_type', 'snowflake'));

        return match ($id_type) {
            'snowflake' => 'int',
            'ulid', 'uuid' => 'string',
            'default' => throw new UnexpectedValueException("Unknown Verbs ID type: '{$id_type}'"),
        };
    }

    public function getIncrementing()
    {
        return false;
    }
}
