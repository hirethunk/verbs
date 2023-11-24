<?php

namespace Thunk\Verbs\Attributes\Autodiscovery;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Replayable
{
    public function __construct(public bool $truncate = true)
    {
    }
}
