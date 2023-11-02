<?php

namespace Thunk\Verbs\Examples\Wingspan\States;

use Thunk\Verbs\State;

class GameState extends State
{
    public bool $started = false;

    public int $setup_count = 0;

    public int $round = 0;

    public int $players = 0;

    public ?int $first_player_id = null;

    public function isSetUp(): bool
    {
        return $this->started
            && $this->setup_count === $this->players
            && $this->first_player_id;
    }
}
