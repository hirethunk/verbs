<?php

namespace Thunk\Verbs\Attributes\Hooks;

use Attribute;
use Thunk\Verbs\Lifecycle\Hook;

#[Attribute(Attribute::TARGET_METHOD)]
class UniqueBy implements HookAttribute
{
    /**
     * @param  string|string[]  $property
     */
    public function __construct(
        public string|array|null $property,
        public ?string $name = null,
        public bool $replay_only = false,
    ) {}

    public function applyToHook(Hook $hook): void
    {
        $hook->deferred_attribute = $this;
    }
}
