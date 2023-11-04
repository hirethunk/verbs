<?php

namespace Thunk\Verbs\Examples\Monopoly\Events\Gameplay;

use InvalidArgumentException;
use Thunk\Verbs\Attributes\Autodiscovery\AppliesToState;
use Thunk\Verbs\Event;
use Thunk\Verbs\Examples\Monopoly\Game\Phase;
use Thunk\Verbs\Examples\Monopoly\States\GameState;
use Thunk\Verbs\Examples\Monopoly\States\PlayerState;

trait PlayerAction
{
    public function validatePlayerAction(GameState $game)
    {
        $this->assert($game->active_player_id === $this->player_id, 'You are not the currently-active player.');
    }
}
