<?php

namespace Thunk\Verbs\Examples\Monopoly\Events\Setup;

use Brick\Money\Money;
use Thunk\Verbs\Attributes\Autodiscovery\AppliesToState;
use Thunk\Verbs\Event;
use Thunk\Verbs\Examples\Monopoly\Game\DeedCollection;
use Thunk\Verbs\Examples\Monopoly\Game\Spaces\Go;
use Thunk\Verbs\Examples\Monopoly\Game\Token;
use Thunk\Verbs\Examples\Monopoly\States\GameState;
use Thunk\Verbs\Examples\Monopoly\States\PlayerState;

#[AppliesToState(GameState::class)]
#[AppliesToState(PlayerState::class)]
class PlayerJoinedGame extends Event
{
    public function __construct(
        public int $game_id,
        public int $player_id,
        public Token $token,
    ) {}

    public function validateGame(GameState $game)
    {
        $this->assert($game->started, 'Game must be started before a player can join.');

        $this->assert(
            assertion: $game->players()->doesntContain(fn (PlayerState $player) => $player->token === $this->token),
            message: 'This token has already been picked by another player'
        );
    }

    public function validatePlayer(PlayerState $player)
    {
        $this->assert(! $player->setup, 'Player has already joined game.');
    }

    public function applyToGame(GameState $game)
    {
        $game->player_ids[] = $this->player_id;
    }

    public function applyToPlayers(PlayerState $player)
    {
        $player->token = $this->token;
        $player->deeds = DeedCollection::make([]);
        $player->money = Money::of(1500, 'USD');
        $player->location = Go::instance();
        $player->setup = true;
    }
}
