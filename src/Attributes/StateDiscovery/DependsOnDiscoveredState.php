<?php

namespace Thunk\Verbs\Attributes\StateDiscovery;

use Illuminate\Support\Collection;

interface DependsOnDiscoveredState
{
    public function dependencies(): array;

    public function setDiscoveredState(Collection $discovered): DependsOnDiscoveredState;
}
