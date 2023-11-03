<?php

namespace Thunk\Verbs\Examples\Monopoly\Game\Spaces;

abstract class SpaceDetails
{
    protected string $name;

    protected int $position;

    public function name(): string
    {
        return $this->name;
    }

    public function position(): int
    {
        return $this->position;
    }
}
