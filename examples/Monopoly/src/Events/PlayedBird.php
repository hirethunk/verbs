<?php

namespace Thunk\Verbs\Examples\Monopoly\Events;

use Thunk\Verbs\Attributes\Autodiscovery\AppliesToState;
use Thunk\Verbs\Event;
use Thunk\Verbs\Examples\Monopoly\Game\Birds\Bird;
use Thunk\Verbs\Examples\Monopoly\States\PlayerState;
use Thunk\Verbs\Examples\Monopoly\States\RoundState;

#[AppliesToState(PlayerState::class)]
#[AppliesToState(RoundState::class)]
class PlayedBird extends Event
{
    use TurnEvent;

    public function __construct(
        public int $player_id,
        public int $round_id,
        public Bird $bird,
        public array $food,
    ) {
    }

    public function validatePlayer(PlayerState $player): bool
    {
        return collect($player->bird_cards)->map(fn (Bird $bird) => $bird::class)->contains($this->bird::class)
            && $player->food->containsAll($this->food);
    }

    public function applyToPlayer(PlayerState $player)
    {
        $player->bird_cards = $player->bird_cards->except($this->bird);
        $player->food = $player->food->consume($this->food);

        $player->board->habitat($this->bird->habitat)->push($this->bird);
    }
}
