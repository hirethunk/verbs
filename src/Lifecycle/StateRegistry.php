<?php

namespace Thunk\Verbs\Lifecycle;

use Thunk\Verbs\State;

class StateRegistry
{
    public function __construct(
        array $caches = []
    ) {}

    public function get(string $class, string $id): State
    {
        // FIXME
    }

    public function put(State $state) {}
}
