<?php

namespace Thunk\Verbs\Examples\Wingspan\Events;

use Thunk\Verbs\Attributes\Autodiscovery\AppliesToState;
use Thunk\Verbs\Event;
use Thunk\Verbs\Examples\Wingspan\States\GameState;
use Thunk\Verbs\Examples\Wingspan\States\PlayerState;

#[AppliesToState(GameState::class)]
#[AppliesToState(PlayerState::class)]
class SelectedAsFirstPlayer extends Event
{
    public function __construct(
        public int $game_id,
        public int $player_id,
    ) {
    }

    public function validateGame(GameState $state)
    {
        return $state->first_player_id === null;
    }

    public function applyToGame(GameState $state)
    {
        $state->first_player_id = $this->player_id;
    }

    public function applyToPlayer(PlayerState $state)
    {
        $state->first_player = true;
    }
}
