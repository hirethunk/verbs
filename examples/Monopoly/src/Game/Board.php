<?php

namespace Thunk\Verbs\Examples\Monopoly\Game;

use Illuminate\Support\Collection;
use Thunk\Verbs\Examples\Monopoly\Game\Spaces\Space;

class Board
{
    use SetsUpBoard;

    public Collection $spaces;

    protected int $max_position;

    public function __construct()
    {
        $this->spaces = $this->setUpAllSpaces()->keyBy(fn (Space $space) => $space->position());
        $this->max_position = $this->spaces->max(fn (Space $space) => $space->position());
    }

    public function findNextSpace(Space $current, int $move): Space
    {
        $next_position = $current->position() + $move;

        if ($next_position > $this->max_position) {
            $next_position -= $this->max_position;
        }

        return $this->spaces->get($next_position);
    }
}
