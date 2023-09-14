<?php

namespace Thunk\Verbs\Attributes;

use Thunk\Verbs\Lifecycle\Hook;

interface HookAttribute
{
    public function applyToHook(Hook $hook): void;
}
