<?php

namespace Thunk\Verbs\Examples\Wingspan\Events;

use InvalidArgumentException;
use Thunk\Verbs\Attributes\Autodiscovery\AppliesToState;
use Thunk\Verbs\Event;
use Thunk\Verbs\Examples\Wingspan\States\GameState;
use Thunk\Verbs\Examples\Wingspan\States\PlayerState;

#[AppliesToState(GameState::class)]
#[AppliesToState(PlayerState::class)]
class PlayerSetUp extends Event
{
    public function __construct(
        public int $game_id,
        public int $player_id,
        public array $bird_cards,
        public string $bonus_card,
        public array $food,
    ) {
        $food_count = count($this->food);

        if ($food_count > 5) {
            throw new InvalidArgumentException('You cannot keep more than 5 pieces of food.');
        }

        $allowed_birds = 5 - $food_count;
        $bird_count = count($this->bird_cards);

        if ($bird_count > $allowed_birds) {
            throw new InvalidArgumentException('For each bird card you keep, you must discard 1 food token.');
        }
    }

    public function validatePlayer(PlayerState $state)
    {
        return ! $state->setup;
    }

    public function validateGame(GameState $state)
    {
        return $state->setup_count < $state->players;
    }

    public function applyToGame(GameState $state)
    {
        $state->setup_count++;
    }

    public function applyToPlayer(PlayerState $state)
    {
        $state->setup = true;
    }
}
