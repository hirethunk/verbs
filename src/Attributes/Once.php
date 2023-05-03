<?php

namespace Thunk\Verbs\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Once
{
    public function __construct(
        public string $event_classname
    ){}
}

