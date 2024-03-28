<?php

namespace Thunk\Verbs\Exceptions;

use Glhd\Bits\Bits;
use Illuminate\Database\RecordsNotFoundException;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Uid\AbstractUid;
use Thunk\Verbs\State;

/**
 * @template TState of State
 */
class StateNotFoundException extends RecordsNotFoundException
{
    /** @var class-string<TState> */
    public string $state;

    public Bits|UuidInterface|AbstractUid|int|string|null $id = null;

    public static function forState(string $state, Bits|UuidInterface|AbstractUid|int|string|null $id = null): static
    {
        $message = "No results for state [{$state}]";

        if ($id) {
            $message .= " with ID '{$id}'";
        }

        $exception = new static($message);

        $exception->state = $state;
        $exception->id = $id;

        return $exception;
    }
}
