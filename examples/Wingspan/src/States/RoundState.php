<?php

namespace Thunk\Verbs\Examples\Wingspan\States;

use Thunk\Verbs\State;

class RoundState extends State
{
    public int $game_id;

    public int $number;

    public int $active_player_id;

    public function game(): GameState
    {
        return GameState::load($this->game_id);
    }

    public function isFinished(): bool
    {
        // TODO: Check that all actions have been completed

        return false;
    }
}
