<?php

namespace Thunk\Verbs\Examples\Monopoly\Events\Gameplay;

use Thunk\Verbs\Attributes\Autodiscovery\AppliesToState;
use Thunk\Verbs\Event;
use Thunk\Verbs\Examples\Monopoly\Game\Phase;
use Thunk\Verbs\Examples\Monopoly\Game\Spaces\Property;
use Thunk\Verbs\Examples\Monopoly\States\GameState;
use Thunk\Verbs\Examples\Monopoly\States\PlayerState;

#[AppliesToState(GameState::class)]
#[AppliesToState(PlayerState::class)]
class PaidRent extends Event
{
    use PlayerAction;

    public function __construct(
        public int $game_id,
        public int $player_id,
        public Property $property,
    ) {}

    public function validatePlayer(PlayerState $player)
    {
        $this->assert($player->location === $this->property, 'You must land on a property to pay rent on it.');
        $this->assert($player->money->isGreaterThanOrEqualTo($this->property->rent()), "You do not have enough money to pay rent at {$this->property->name()}.");
        $this->assert($player !== $this->property->owner(), 'You cannot pay rent on a property that you do own.');
    }

    public function validateGame(GameState $game)
    {
        $this->assert(! $game->bank->hasDeed($this->property), "{$this->property->name()} is still for sale—you cannot pay rent on it.");
        $this->assert($game->phase_complete, 'You must finish what you’re doing before purchasing a property.');
        $this->assert($game->phase->canTransitionTo(Phase::PayRent), 'You are not allowed to pay rent right now.');
    }

    public function applyToGame(GameState $game)
    {
        $game->phase = Phase::PayRent;
        $game->phase_complete = true;
    }

    public function applyToPlayer(PlayerState $player)
    {
        $owner = $this->property->owner();

        $owner->money = $owner->money->plus($this->property->rent());
        $player->money = $player->money->minus($this->property->rent());
    }
}
