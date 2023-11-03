<?php

namespace Thunk\Verbs\Examples\Monopoly\Events;

use InvalidArgumentException;
use Thunk\Verbs\Attributes\Autodiscovery\AppliesToState;
use Thunk\Verbs\Event;
use Thunk\Verbs\Examples\Monopoly\Game\Birds\BirdCollection;
use Thunk\Verbs\Examples\Monopoly\Game\FoodCollection;
use Thunk\Verbs\Examples\Monopoly\States\GameState;
use Thunk\Verbs\Examples\Monopoly\States\PlayerState;

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

        // TODO: Validate food and birds are legit game pieces
    }

    public function validatePlayer(PlayerState $player)
    {
        return ! $player->setup;
    }

    public function validateGame(GameState $game)
    {
        return $game->currentRoundNumber() === null;
    }

    public function applyToPlayer(PlayerState $player)
    {
        $player->bird_cards = BirdCollection::make($this->bird_cards);
        $player->bonus_cards = [$this->bonus_card];
        $player->food = FoodCollection::make($this->food);
        $player->setup = true;
    }
}
