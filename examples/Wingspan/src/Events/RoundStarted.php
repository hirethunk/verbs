<?php

namespace Thunk\Verbs\Examples\Wingspan\Events;

use Thunk\Verbs\Attributes\Autodiscovery\AppliesToState;
use Thunk\Verbs\Event;
use Thunk\Verbs\Examples\Wingspan\States\GameState;
use Thunk\Verbs\Examples\Wingspan\States\RoundState;

#[AppliesToState(GameState::class)]
#[AppliesToState(RoundState::class)]
class RoundStarted extends Event
{
    public function __construct(
        public int $game_id,
        public ?int $round_id = null,
    ) {
    }

    public function validateGame(GameState $game)
    {
        return $game->current_round_complete && $game->round < 4;
    }

    public function applyToGame(GameState $game)
    {
        $game->round++;
        $game->current_round_complete = false;
    }
}
