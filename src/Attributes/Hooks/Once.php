<?php

namespace Thunk\Verbs\Attributes\Hooks;

use Attribute;
use Thunk\Verbs\Lifecycle\Hook;
use Thunk\Verbs\Lifecycle\Phase;

#[Attribute(Attribute::TARGET_METHOD)]
class Once implements HookAttribute
{
    public function applyToHook(Hook $hook): void
    {
        $hook->skipPhases(Phase::Replay);
    }
}
