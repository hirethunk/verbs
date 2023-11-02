<?php

namespace Thunk\Verbs\Examples\Wingspan\Events;

use Glhd\Bits\Snowflake;
use InvalidArgumentException;
use Thunk\Verbs\Attributes\Autodiscovery\AppliesToState;
use Thunk\Verbs\Event;
use Thunk\Verbs\Examples\Wingspan\States\GameState;
use Thunk\Verbs\Examples\Wingspan\States\PlayerState;
use Thunk\Verbs\Examples\Wingspan\States\RoundState;
use Thunk\Verbs\State;
use Thunk\Verbs\Support\StateCollection;

#[AppliesToState(GameState::class)]
#[AppliesToState(RoundState::class)]
class RoundStarted extends Event
{
    public function __construct(
        public int $game_id,
        public ?int $round_id = null,
    ) {

    }

    public function validateGame(GameState $game)
    {
        // TODO: It'd be nice to have access to the previous round here

        return $game->round < 4;
    }

    public function applyToGame(GameState $game)
    {
        $game->round++;
    }
}
