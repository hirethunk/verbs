<?php

namespace Thunk\Verbs\Examples\Wingspan\Events;

use Thunk\Verbs\Attributes\Autodiscovery\AppliesToState;
use Thunk\Verbs\Event;
use Thunk\Verbs\Examples\Wingspan\Game\Birds\Bird;
use Thunk\Verbs\Examples\Wingspan\States\PlayerState;

#[AppliesToState(PlayerState::class)]
class LaidEggs extends Event
{
    public function __construct(
        public int $player_id,
        public int $round_id,
        public array $birds,
    ) {
        collect($this->birds)->ensure(Bird::class);
    }

    public function validatePlayer(PlayerState $player): bool
    {
        // TODO: Check that bird can accept more eggs

        return $player->available_action_cubes > 0
            && collect($this->birds)->pluck('id')
                ->diff($player->board->inAnyHabitat()->pluck('id'))
                ->isEmpty();
    }

    public function applyToPlayer(PlayerState $player)
    {
        collect($this->birds)->each(fn (Bird $bird) => $bird->egg_count++);

        $player->available_action_cubes--;
    }
}
