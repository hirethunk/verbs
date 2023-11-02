<?php

namespace Thunk\Verbs\Examples\Wingspan\Game;

use Thunk\Verbs\Attributes\Autodiscovery\AppliesToState;
use Thunk\Verbs\Event;
use Thunk\Verbs\Examples\Wingspan\Game\Birds\Bird;
use Thunk\Verbs\Examples\Wingspan\States\PlayerState;
use Thunk\Verbs\Examples\Wingspan\States\RoundState;

#[AppliesToState(PlayerState::class)]
#[AppliesToState(RoundState::class)]
class PlayedBird extends Event
{
    public function __construct(
        public int $player_id,
        public int $round_id,
        public Bird $bird,
        public array $food,
    ) {
    }

    public function validatePlayer(PlayerState $player): bool
    {
        return $player->available_action_cubes > 0
            && collect($player->bird_cards)->map(fn (Bird $bird) => $bird::class)->contains($this->bird::class)
            && $player->food->containsAll($this->food);
    }

    public function applyToPlayer(PlayerState $player)
    {
        $player->bird_cards = $player->bird_cards->except($this->bird);
        $player->food = $player->food->consume($this->food);
        $player->available_action_cubes--;

        match ($this->bird->habitat) {
            Habitat::Trees => $player->tree_birds->push($this->bird),
            Habitat::Grass => $player->grass_birds->push($this->bird),
            Habitat::Water => $player->water_birds->push($this->bird),
        };
    }
}
