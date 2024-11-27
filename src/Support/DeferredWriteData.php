<?php

namespace Thunk\Verbs\Support;

class DeferredWriteData
{
public function __construct(
    public ?string $class_name,
    public string|null $unique_by,
)
{
}
}
