<?php

namespace Thunk\Verbs\Examples\Wingspan\States;

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
}
