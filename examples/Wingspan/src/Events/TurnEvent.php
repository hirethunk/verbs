<?php

namespace Thunk\Verbs\Examples\Wingspan\Events;

use Thunk\Verbs\Examples\Wingspan\States\PlayerState;
use Thunk\Verbs\Examples\Wingspan\States\RoundState;

trait TurnEvent
{
    public function validatePlayerTurn(PlayerState $player): bool
    {
        return $player->available_action_cubes > 0;
    }

    public function validateRoundTurn(RoundState $round): bool
    {
        $player = $this->state(PlayerState::class);

        return $player->id === $round->active_player_id;
    }

    public function applyToRound(RoundState $round)
    {
        $players = $round->game()->players();

        // Next player is either the next one in the list, or if there are no more
        // players in the list, then it's the first player in the list again.
        $next_player = $players
            ->skipUntil(fn (PlayerState $player) => $player->id === $round->active_player_id)
            ->first() ?? $players->first();

        $round->active_player_id = $next_player->id;
    }
}
