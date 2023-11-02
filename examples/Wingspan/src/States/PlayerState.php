<?php

namespace Thunk\Verbs\Examples\Wingspan\States;

use Illuminate\Support\Collection;
use Thunk\Verbs\Examples\Wingspan\Game\Birds\BirdCollection;
use Thunk\Verbs\Examples\Wingspan\Game\FoodCollection;
use Thunk\Verbs\State;

class PlayerState extends State
{
    public bool $setup = false;

    public bool $first_player = false;

    public int $available_action_cubes = 0;

    public BirdCollection $bird_cards;

    public array $bonus_cards = [];

    public FoodCollection $food;

    public BirdCollection $tree_birds;

    public BirdCollection $grass_birds;

    public BirdCollection $water_birds;

    public function playedBirds(): Collection
    {
        return $this->tree_birds->merge($this->grass_birds)->merge($this->water_birds);
    }
}
