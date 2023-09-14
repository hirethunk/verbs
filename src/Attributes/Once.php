<?php

namespace Thunk\Verbs\Attributes;

use Attribute;
use Thunk\Verbs\Lifecycle\Hook;

#[Attribute(Attribute::TARGET_METHOD)]
class Once implements HookAttribute
{
    public function applyToHook(Hook $hook): void
    {
        $hook->replayable = false;
    }
}
