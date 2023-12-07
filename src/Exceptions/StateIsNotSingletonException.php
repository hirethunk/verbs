<?php

namespace Thunk\Verbs\Exceptions;

use RuntimeException;

class StateIsNotSingletonException extends RuntimeException
{
    public function __construct(string $type)
    {
        parent::__construct("Expected '{$type}' to be a singleton, but found multiple states.");
    }
}
