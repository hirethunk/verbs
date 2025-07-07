<?php

namespace Thunk\Verbs\Lifecycle;

use Thunk\Verbs\State;

class StateRegistry
{
    public function __construct(
        array $caches = [],
        public array $cache = [],
    ) {}

    public function get(string $class, string $id): ?State
    {
        $key = "$class:$id";

        return $this->cache[$key] ?? null;
    }

    public function put(State $state)
    {
        $this->cache[$state::class.':'.$state->id] = $state;
    }
}
