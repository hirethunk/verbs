<?php

namespace Thunk\Verbs\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class DiscoversState
{
    public function __construct(
        public ?string $property_name = null
    ) {
    }
}
