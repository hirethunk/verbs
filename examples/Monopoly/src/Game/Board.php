<?php

namespace Thunk\Verbs\Examples\Monopoly\Game;

use Illuminate\Support\Collection;
use Thunk\Verbs\Examples\Monopoly\Game\Spaces\Space;

class Board
{
    use SetsUpBoard;

    public Collection $spaces;

    public function __construct()
    {
        $this->spaces = $this->setUpAllSpaces();
    }
}
