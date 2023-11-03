<?php

namespace Thunk\Verbs\Examples\Monopoly\Events;

use InvalidArgumentException;
use Thunk\Verbs\Attributes\Autodiscovery\AppliesToState;
use Thunk\Verbs\Event;
use Thunk\Verbs\Examples\Monopoly\States\GameState;
use Thunk\Verbs\Examples\Monopoly\States\RoundState;

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
        if ($this->number === 1) {
            return $game->current_round_id === null && $game->isSetUp();
        }

        $current_round = $game->currentRound();

        return $current_round
            && $current_round->isFinished()
            && $current_round->number === $this->number - 1;
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
