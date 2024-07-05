<?php

namespace Thunk\Verbs\Examples\Monopoly\Events\Gameplay;

use Thunk\Verbs\Attributes\Autodiscovery\AppliesToState;
use Thunk\Verbs\Event;
use Thunk\Verbs\Examples\Monopoly\Game\Phase;
use Thunk\Verbs\Examples\Monopoly\Game\Spaces\Space;
use Thunk\Verbs\Examples\Monopoly\States\GameState;
use Thunk\Verbs\Examples\Monopoly\States\PlayerState;

#[AppliesToState(GameState::class)]
#[AppliesToState(PlayerState::class)]
class PlayerMoved extends Event
{
    use PlayerAction;

    public function __construct(
        public int $game_id,
        public int $player_id,
        public Space $to,
    ) {}

    public function validateGame(GameState $game)
    {
        $this->assert($game->last_roll, 'You must roll the dice first.');
        $this->assert($game->phase === Phase::Move && ! $game->phase_complete, 'You are not allowed to move right now.');
        $this->assert($this->to === $this->expectedSpace(), "You must move to '{$this->expectedSpace()->name()}'");
    }

    public function applyToGame(GameState $game)
    {
        $game->phase = Phase::Move;
        $game->phase_complete = true;
        $game->last_roll = null;
    }

    public function applyToPlayer(PlayerState $player)
    {
        $player->location = $this->to;
    }

    protected function expectedSpace(): Space
    {
        $game = $this->state(GameState::class);
        $player = $this->state(PlayerState::class);

        return $game->board->findNextSpace($player->location, array_sum($game->last_roll));
    }
}
