<?php

namespace Thunk\Verbs\Examples\Monopoly\Events\Setup;

use Thunk\Verbs\Attributes\Autodiscovery\AppliesToState;
use Thunk\Verbs\Event;
use Thunk\Verbs\Examples\Monopoly\Game\Bank;
use Thunk\Verbs\Examples\Monopoly\Game\Board;
use Thunk\Verbs\Examples\Monopoly\States\GameState;

#[AppliesToState(GameState::class)]
class GameStarted extends Event
{
    public function __construct(
        public ?int $game_id = null,
    ) {}

    public function validate(GameState $game)
    {
        $this->assert(! $game->started, 'The game has already started');
    }

    public function applyToGame(GameState $game)
    {
        $game->started = true;
        $game->started_at = now()->toImmutable();
        $game->player_ids = [];
        $game->board = new Board();
        $game->bank = new Bank();
    }
}
