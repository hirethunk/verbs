<?php

namespace Thunk\Verbs\Attributes\Hooks;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Tag
{
    /** @var string[] */
    public array $tags;

    public function __construct(string|array $tag)
    {
        $this->tags = is_array($tag) ? $tag : [$tag];
    }
}
