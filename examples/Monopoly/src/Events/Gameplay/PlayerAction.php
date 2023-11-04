<?php

namespace Thunk\Verbs\Examples\Monopoly\Events\Gameplay;

use Thunk\Verbs\Examples\Monopoly\States\GameState;

trait PlayerAction
{
    public function validatePlayerAction(GameState $game)
    {
        $this->assert($game->active_player_id === $this->player_id, 'You are not the currently-active player.');
    }
}
