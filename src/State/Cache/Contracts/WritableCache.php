<?php

namespace Thunk\Verbs\State\Cache\Contracts;

use Thunk\Verbs\State;

interface WritableCache
{
    public function put(State $state): State;

    public function willPrune(): bool;

    public function prune(): static;

    public function reset(): static;
}
