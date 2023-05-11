<?php

namespace Thunk\Verbs\Attributes;

use Attribute;

/**
 * @codeCoverageIgnore
 */
#[Attribute(Attribute::TARGET_CLASS)]
class CreatesContext
{
    public function __construct(
        protected string $class_name
    ) {
    }
}
