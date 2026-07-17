<?php

namespace Thunk\Verbs\State;

use Glhd\Bits\Bits;
use InvalidArgumentException;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Uid\AbstractUid;
use Thunk\Verbs\Facades\Id;
use Thunk\Verbs\SingletonState;
use Thunk\Verbs\State;

/**
 * A (type, id) reference to a state, optionally carrying the last event id
 * the state is known to have applied (always normalized to a comparable scalar).
 */
class StateIdentity
{
    public int|string|null $last_event_id;

    public static function from(object $source): static
    {
        return match (true) {
            $source instanceof self => $source,
            $source instanceof State => new static(state_type: $source::class, state_id: Id::from($source->id)),
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
        Bits|UuidInterface|AbstractUid|int|string|null $last_event_id = null,
    ) {
        // Normalizing here makes "last event ids compare as scalars" a property
        // of the type itself, so no call site has to remember driver quirks.
        $this->last_event_id = Id::normalizeEventId($last_event_id);
    }

    /**
     * The canonical identity key. A singleton collapses to its type—its events
     * and snapshots may be recorded under incidental state_ids that must all
     * refer to one identity. Keyed states are "type:id", where concatenation
     * means the int and string driver shapes of an id can't produce different keys.
     */
    public function key(): string
    {
        return is_a($this->state_type, SingletonState::class, true)
            ? $this->state_type
            : $this->state_type.':'.$this->state_id;
    }
}
