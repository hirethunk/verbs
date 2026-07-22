<?php

namespace Thunk\Verbs\State\Cache\Contracts;

use Thunk\Verbs\State;

interface ReadableCache
{
    public function get(string $class, int|string|null $id = null): ?State;

    public function has(string $class, int|string|null $id = null): bool;

    /** @return State[] */
    public function values(): array;
}
