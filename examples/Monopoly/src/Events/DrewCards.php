<?php

namespace Thunk\Verbs\Examples\Monopoly\Events;

use Thunk\Verbs\Attributes\Autodiscovery\AppliesToState;
use Thunk\Verbs\Event;
use Thunk\Verbs\Examples\Monopoly\Game\Birds\Bird;
use Thunk\Verbs\Examples\Monopoly\States\PlayerState;

#[AppliesToState(PlayerState::class)]
class DrewCards extends Event
{
    use TurnEvent;

    public function __construct(
        public int $player_id,
        public array $birds,
    ) {
        collect($this->birds)->ensure(Bird::class);
    }

    public function validatePlayer(PlayerState $player): bool
    {
        $allowed = match (true) {
            $player->board->inWater()->count() > 3 => 4,
            $player->board->inWater()->count() > 1 => 3,
            default => 2,
        };

        return count($this->birds) <= $allowed;
    }

    public function applyToPlayer(PlayerState $player)
    {
        $player->bird_cards->push(...$this->birds);
    }
}
