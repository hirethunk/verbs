<?php

namespace Thunk\Verbs\Attributes\Hooks;

use Thunk\Verbs\Lifecycle\Hook;

interface HookAttribute
{
    public function applyToHook(Hook $hook): void;
}
