<?php

namespace Thunk\Verbs\Support;

class NamedDependency
{
    public function __construct(
        public readonly string $name,
        public readonly mixed $value,
    ) {}
}
