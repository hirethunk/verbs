<?php

namespace Thunk\Verbs\Attributes\Hooks;

use Attribute;
use Thunk\Verbs\Lifecycle\Hook;
use Thunk\Verbs\Lifecycle\Phase;

#[Attribute(Attribute::TARGET_METHOD)]
class On implements HookAttribute
{
    public function __construct(
        public Phase $phase
    ) {}

    public function applyToHook(Hook $hook): void
    {
        $hook->forcePhases($this->phase);
    }
}
