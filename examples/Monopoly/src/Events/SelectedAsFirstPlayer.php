<?php

namespace Thunk\Verbs\Examples\Monopoly\Events;

use Thunk\Verbs\Attributes\Autodiscovery\AppliesToState;
use Thunk\Verbs\Event;
use Thunk\Verbs\Examples\Monopoly\States\GameState;
use Thunk\Verbs\Examples\Monopoly\States\PlayerState;

#[AppliesToState(GameState::class)]
#[AppliesToState(PlayerState::class)]
class SelectedAsFirstPlayer extends Event
{
    public function __construct(
        public int $game_id,
        public int $player_id,
    ) {
    }

    public function validateGame(GameState $game)
    {
        return $game->first_player_id === null;
    }

    public function applyToGame(GameState $game)
    {
        $game->first_player_id = $this->player_id;
    }

    public function applyToPlayer(PlayerState $player)
    {
        $player->first_player = true; // TODO: This maybe can go in favor of game.first_player_id
    }
}
