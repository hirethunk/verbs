<?php

namespace Thunk\Verbs\Examples\Monopoly\Events\Gameplay;

use Thunk\Verbs\Attributes\Autodiscovery\AppliesToState;
use Thunk\Verbs\Event;
use Thunk\Verbs\Examples\Monopoly\Game\Phase;
use Thunk\Verbs\Examples\Monopoly\States\GameState;
use Thunk\Verbs\Examples\Monopoly\States\PlayerState;

#[AppliesToState(GameState::class)]
#[AppliesToState(PlayerState::class)]
class FlippedTable extends Event
{
    use PlayerAction;

    public function __construct(
        public int $game_id,
        public int $player_id,
    ) {}

    public function validateGame(GameState $game)
    {
        $this->assert($game->started_at->diffInHours(now()) >= 6, 'You cannot rage quit so early!');
    }

    public function applyToGame(GameState $game)
    {
        $game->phase = Phase::OnTheFloorScatteredInDisarray;
    }
}
