<?php

namespace Thunk\Verbs\Examples\Wingspan\Events;

use Thunk\Verbs\Attributes\Autodiscovery\AppliesToState;
use Thunk\Verbs\Event;
use Thunk\Verbs\Examples\Wingspan\Game\Birds\Bird;
use Thunk\Verbs\Examples\Wingspan\Game\Habitat;
use Thunk\Verbs\Examples\Wingspan\States\PlayerState;
use Thunk\Verbs\Examples\Wingspan\States\RoundState;

#[AppliesToState(PlayerState::class)]
class DrewCards extends Event
{
    public function __construct(
        public int $player_id,
        public array $birds,
    ) {
        collect($this->birds)->ensure(Bird::class);
    }

    public function validatePlayer(PlayerState $player): bool
    {
        $allowed = match (true) {
            $player->water_birds->count() > 3 => 4,
            $player->water_birds->count() > 1 => 3,
            default => 2,
        };

        return $player->available_action_cubes > 0
            && count($this->birds) <= $allowed;
    }

    public function applyToPlayer(PlayerState $player)
    {
        $player->bird_cards->push(...$this->birds);
        $player->available_action_cubes--;
    }
}
