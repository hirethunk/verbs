<?php

namespace Thunk\Verbs\Examples\Monopoly\Events\Gameplay;

use Thunk\Verbs\Attributes\Autodiscovery\AppliesToState;
use Thunk\Verbs\Event;
use Thunk\Verbs\Examples\Monopoly\Game\Phase;
use Thunk\Verbs\Examples\Monopoly\States\GameState;
use Thunk\Verbs\Examples\Monopoly\States\PlayerState;

#[AppliesToState(GameState::class)]
#[AppliesToState(PlayerState::class)]
class EndedTurn extends Event
{
    use PlayerAction;

    public function __construct(
        public int $game_id,
        public int $player_id,
    ) {}

    public function validateGame(GameState $game)
    {
        $this->assert($game->phase_complete, 'You have to finish what you’re doing before you can end your turn.');
        $this->assert($game->phase->canTransitionTo(Phase::EndTurn), 'You must complete your turn before ending it.');
    }

    public function applyToGame(GameState $game)
    {
        $game->moveToNextPlayer();
        $game->phase = Phase::Move;
        $game->phase_complete = false;
    }
}
