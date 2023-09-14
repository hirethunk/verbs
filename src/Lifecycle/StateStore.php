<?php

namespace Thunk\Verbs\Lifecycle;

use Thunk\Verbs\State;
use Thunk\Verbs\VerbSnapshot;
use UnexpectedValueException;

class StateStore
{
    public function load(int|string $id, string $type): State
    {
        // FIXME: Singleton

        if ($snapshot = VerbSnapshot::find($id)) {
            if ($type !== $snapshot->type) {
                throw new UnexpectedValueException('State does not have a valid type.');
            }

            return $snapshot->type::hydrate($snapshot->data);
        }

        $state = $type::initialize();
        $state->id = $id;

        return $state;
    }
}
