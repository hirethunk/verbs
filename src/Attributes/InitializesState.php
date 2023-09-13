<?php

namespace Thunk\Verbs\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class InitializesState
{
    public function __construct(
        public ?string $class_name = null
    ) {
    }
}
