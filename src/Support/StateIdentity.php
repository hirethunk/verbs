<?php

namespace Thunk\Verbs\Support;

use InvalidArgumentException;
use Thunk\Verbs\State;

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

        if (is_int($state_id) && is_string($state_type)) {
            return new static(state_type: $state_type, state_id: $state_id);
        }

        throw new InvalidArgumentException('State identity objects must have a "state_id" and "state_type" value.');
    }

    public function __construct(
        public readonly string $state_type,
        public readonly int|string $state_id,
    ) {}
}
