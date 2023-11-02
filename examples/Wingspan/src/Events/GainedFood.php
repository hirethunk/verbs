<?php

namespace Thunk\Verbs\Examples\Wingspan\Events;

use Thunk\Verbs\Attributes\Autodiscovery\AppliesToState;
use Thunk\Verbs\Event;
use Thunk\Verbs\Examples\Wingspan\Game\Birds\Bird;
use Thunk\Verbs\Examples\Wingspan\Game\Food;
use Thunk\Verbs\Examples\Wingspan\States\PlayerState;
use Thunk\Verbs\Examples\Wingspan\States\RoundState;

#[AppliesToState(PlayerState::class)]
class GainedFood extends Event
{
    public function __construct(
        public int $player_id,
        public Food $food,
    ) {
    }

    public function validate(PlayerState $player)
    {
        // TODO: Eventually confirm that this food is available

        return $player->available_action_cubes > 0;
    }

    public function applyToPlayer(PlayerState $player)
    {
        $player->food->push($this->food);
        $player->available_action_cubes--;
    }
}
