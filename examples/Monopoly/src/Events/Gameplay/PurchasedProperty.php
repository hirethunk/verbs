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
class PurchasedProperty extends Event
{
    use PlayerAction;

    public function __construct(
        public int $game_id,
        public int $player_id,
        public Property $property,
    ) {}

    public function validatePlayer(PlayerState $player)
    {
        $this->assert($player->location === $this->property, 'You must land on a property to buy it.');
        $this->assert($player->money->isGreaterThanOrEqualTo($this->property->price()), "You do not have enough money to buy {$this->property->name()}.");
    }

    public function validateGame(GameState $game)
    {
        $this->assert($game->bank->hasDeed($this->property), "{$this->property->name()} is not for sale.");
        $this->assert($game->phase_complete, 'You must finish what you’re doing before purchasing a property.');
        $this->assert($game->phase->canTransitionTo(Phase::Purchase), 'You are not allowed to purchase properties right now.');
    }

    public function applyToGame(GameState $game)
    {
        $game->phase = Phase::Purchase;
        $game->phase_complete = true;
        $game->bank->purchaseDeed($this->property);
    }

    public function applyToPlayer(PlayerState $player)
    {
        $player->deeds->push($this->property);
        $player->money = $player->money->minus($this->property->price());
        $this->property->setOwner($player);
    }
}
