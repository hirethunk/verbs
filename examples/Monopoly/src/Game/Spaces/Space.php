<?php

namespace Thunk\Verbs\Examples\Monopoly\Game\Spaces;

abstract class Space
{
    protected string $name;

    protected int $position;

    public static function instance()
    {
        return new static();
    }

    public function name(): string
    {
        return $this->name;
    }

    public function position(): int
    {
        return $this->position;
    }
}
