<?php

namespace Thunk\Verbs\Examples\Wingspan\Events;

use InvalidArgumentException;
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
        public int $number = 1,
        public ?int $round_id = null,
    ) {
        if ($this->number < 1 || $this->number > 4) {
            throw new InvalidArgumentException('Round number must be between 1 and 4.');
        }
    }

    public function validateGame(GameState $game)
    {
        if (! $last_round = $game->currentRound()) {
            return $game->isSetUp();
        }

        return $last_round->isFinished() && $last_round->number < $this->number;
    }

    public function applyToGame(GameState $game)
    {
        $game->current_round_id = $this->round_id;
    }

    public function applyToRound(RoundState $round)
    {
        $game = $this->state(GameState::class);

        $round->game_id = $this->game_id;
        $round->number = $this->number;
        $round->active_player_id = $game->first_player_id;
    }
}
