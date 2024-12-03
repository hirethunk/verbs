<?php

namespace Thunk\Verbs\Attributes\Hooks;

use Attribute;
use Thunk\Verbs\Facades\Verbs;
use Thunk\Verbs\Lifecycle\Hook;

#[Attribute(Attribute::TARGET_METHOD)]
class DeferFor implements HookAttribute
{
    public const EVENT_CLASS = 'event_class_name';

    /**
     * This attribute is used to defer the handling of a hook until
     * after data is committed/replayed. You can use this attribute
     * to prevent duplicate writes by ensuring that the hook is only
     * handled once for a given set of state properties.
     *
     * @param string|string[] $property_name The state property name(s) to be unique by
     * @param string $name Defaults to the event's class name
     * @param bool $replay_only Only defer for replayed events
     */
    public function __construct(
        public string|array|null $property_name,
        public string           $name = self::EVENT_CLASS,
        public bool              $replay_only = false,
    ) {}

    public function applyToHook(Hook $hook): void
    {
        $hook->deferred_attribute = $this;
    }
}
