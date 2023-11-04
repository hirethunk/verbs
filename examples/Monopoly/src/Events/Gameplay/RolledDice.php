<?php

namespace Thunk\Verbs\Examples\Monopoly\Events\Gameplay;

use InvalidArgumentException;
use Thunk\Verbs\Attributes\Autodiscovery\AppliesToState;
use Thunk\Verbs\Event;
use Thunk\Verbs\Examples\Monopoly\Game\Phase;
use Thunk\Verbs\Examples\Monopoly\States\GameState;
use Thunk\Verbs\Examples\Monopoly\States\PlayerState;

#[AppliesToState(GameState::class)]
#[AppliesToState(PlayerState::class)]
class RolledDice extends Event
{
    use PlayerAction;

    public function __construct(
        public int $game_id,
        public int $player_id,
        public array $dice,
    ) {
        if (count($this->dice) !== 2) {
            throw new InvalidArgumentException('You can only roll two dice.');
        }

        [$first, $second] = $this->dice;

        if ($first < 1 || $first > 6 || $second < 1 || $second > 6) {
            throw new InvalidArgumentException('You must roll six-sided dice.');
        }

        // TODO: If you roll doubles, you get to go again. If you roll 3 times, you go to jail.
    }

    public function validateGame(GameState $game)
    {
        $this->assert($game->last_roll === null, 'You already rolled the dice.');
    }

    public function applyToGame(GameState $game)
    {
        $game->last_roll = $this->dice;
    }
}
