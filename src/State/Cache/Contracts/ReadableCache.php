<?php

namespace Thunk\Verbs\State\Cache\Contracts;

use Thunk\Verbs\State;

interface ReadableCache
{
    public function get(string $class, ?string $id = null): ?State;

    public function has(string $class, string $id): bool;
}
