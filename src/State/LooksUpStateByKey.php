<?php

namespace Thunk\Verbs\State;

use Thunk\Verbs\State;

trait LooksUpStateByKey
{
    /**
     * @template TState instanceof State
     *
     * @param  State|class-string<TState>  $state
     */
    protected function key(State|string $state, string|int|null $id = null): string
    {
        if ($state instanceof State) {
            $id = $state->id;
            $state = $state::class;
        }

        return $id ? "{$state}:{$id}" : $state;
    }
}
