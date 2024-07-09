<?php

namespace Thunk\Verbs\Examples\Monopoly\Events\Setup;

use Thunk\Verbs\Attributes\Autodiscovery\AppliesToState;
use Thunk\Verbs\Event;
use Thunk\Verbs\Examples\Monopoly\States\GameState;

#[AppliesToState(GameState::class)]
class FirstPlayerSelected extends Event
{
    public function __construct(
        public int $game_id,
        public int $player_id,
    ) {}

    public function validateGame(GameState $game)
    {
        $this->assert($game->activePlayer() === null, 'A player has already been selected.');
        $this->assert($game->hasPlayer($this->player_id), 'This player is not part of the game.');
    }

    public function applyToGame(GameState $game)
    {
        $game->active_player_id = $this->player_id;
    }
}
