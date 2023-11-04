<?php

namespace Thunk\Verbs\Examples\Monopoly\Events\Gameplay;

use InvalidArgumentException;
use Thunk\Verbs\Attributes\Autodiscovery\AppliesToState;
use Thunk\Verbs\Event;
use Thunk\Verbs\Examples\Monopoly\Game\Phase;
use Thunk\Verbs\Examples\Monopoly\Game\Spaces\Go;
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
        public int $first_die,
        public int $second_die,
    ) {
        if ($this->first_die < 1 || $this->first_die > 6 || $this->second_die < 1 || $this->second_die > 6) {
            throw new InvalidArgumentException('Dice rolls must be between 1 and 6');
        }

        // TODO: If you roll doubles, you get to go again. If you roll 3 times, you go to jail.
    }

    public function validateGame(GameState $game)
    {
        $this->assert($game->phase === Phase::Move, 'You are not allowed to roll dice right now.');
    }

    public function applyToGameAndPlayer(GameState $game)
    {
        $player = $this->state(PlayerState::class);

        $player->location = $game->board->findNextSpace($player->location, $this->first_die + $this->second_die);
        $game->phase_complete = true;
    }
}
