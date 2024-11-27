<?php

namespace Thunk\Verbs\Attributes\Hooks;

use Attribute;
use Thunk\Verbs\Lifecycle\Hook;
use Thunk\Verbs\Lifecycle\Phase;
use Thunk\Verbs\Support\DeferredWriteData;

#[Attribute(Attribute::TARGET_METHOD)]
class Latest implements HookAttribute
{
    public function __construct(
        public string|null $unique_id = null,
       public  ?string $type = null,
    )
    {
    }

    public function applyToHook(Hook $hook): void
    {
        $hook->deferred = new DeferredWriteData($this->type, $this->unique_id);
    }
}
