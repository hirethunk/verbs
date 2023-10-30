<?php

namespace Thunk\Verbs\Attributes;

use Attribute;
use InvalidArgumentException;
use Thunk\Verbs\State;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Identifies
{
    public function __construct(
        public string $state_type
    ) {
        if (! is_a($this->state_type, State::class, true)) {
            throw new InvalidArgumentException('You must pass state class names to the "Identifies" attribute.');
        }
    }
}
