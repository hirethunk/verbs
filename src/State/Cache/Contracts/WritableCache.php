<?php

namespace Thunk\Verbs\State\Cache\Contracts;

use Thunk\Verbs\State;

interface WritableCache
{
    public function put(State $state): State;

    /**
     * Pin a state so it is never evicted by prune(). Used to protect states
     * referenced by queued-but-uncommitted events for the duration of a batch.
     */
    public function pin(State|string $type, int|string|null $id = null): static;

    public function unpin(State|string $type, int|string|null $id = null): static;

    public function willPrune(): bool;

    public function prune(): static;

    public function reset(): static;
}
