<?php

namespace Thunk\Verbs\Examples\Monopoly\Game;

use Illuminate\Support\Collection;
use Thunk\Verbs\Examples\Monopoly\Game\Spaces\Space;

class Bank
{
    public Collection $spaces;

    public function __construct()
    {
        $this->spaces = collect(Space::cases())->sortBy(fn (Space $space) => $space->position());
    }
}
