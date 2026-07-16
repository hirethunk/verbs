<?php

namespace Thunk\Verbs\State;

use InvalidArgumentException;
use Thunk\Verbs\State;

/**
 * A (type, id) reference to a state, optionally carrying the position
 * (last applied event id) the state is known to have advanced to.
 */
class StateIdentity
{
    public static function from(object $source): static
    {
        return match (true) {
            $source instanceof self => $source,
            $source instanceof State => new static(state_type: $source::class, state_id: $source->id),
            default => static::fromGenericObject($source),
        };
    }

    protected static function fromGenericObject(object $source): static
    {
        $state_id = data_get($source, 'state_id');
        $state_type = data_get($source, 'state_type');

        // state_id may be an int (snowflake) or a string (a UUID, or a bigint that
        // the database driver returned as a string—e.g. MySQL/Postgres via PDO),
        // mirroring State::$id's int|string type.
        if ((is_int($state_id) || is_string($state_id)) && is_string($state_type)) {
            return new static(state_type: $state_type, state_id: $state_id);
        }

        throw new InvalidArgumentException('State identity objects must have a "state_id" and "state_type" value.');
    }

    public function __construct(
        public string $state_type,
        public int|string $state_id,
        public int|string|null $position = null,
    ) {}
}
