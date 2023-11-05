<?php

namespace Thunk\Verbs;

use Illuminate\Database\Eloquent\Casts\Attribute;
use InvalidArgumentException;
use RuntimeException;

trait FromState
{
    public function getVerbsStateKey()
    {
        return $this->getKey();
    }

    protected function stateAttribute(string $state_type, string $id = 'id'): Attribute
    {
        if (! is_a($state_type, State::class, true)) {
            throw new InvalidArgumentException(class_basename($this).'::stateAttribute must be passed a state class name');
        }

        return Attribute::make(
            get: fn () => $state_type::load($this->getAttribute($id)),
            set: fn () => throw new RuntimeException('You cannot set Verbs state on a model.'),
        );
    }
}
