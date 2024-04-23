<?php

namespace Thunk\Verbs\Livewire;

#[\Attribute(\Attribute::TARGET_METHOD)]
class Dehydrate
{
    public string $name;

    public function __construct(
        public ?string $alias = null,
    ) {
    }

    public function getAlias(): string
    {
        return $this->alias ?? $this->name;
    }
}
