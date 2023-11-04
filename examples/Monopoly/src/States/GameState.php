<?php

namespace Thunk\Verbs\Examples\Monopoly\States;

use Illuminate\Support\Collection;
use Thunk\Verbs\Examples\Monopoly\Game\Board;
use Thunk\Verbs\Examples\Monopoly\Game\Phase;
use Thunk\Verbs\State;

class GameState extends State
{
    public bool $started = false;

    public Board $board;

    public array $player_ids = [];

    public ?int $active_player_id = null;

    public Phase $phase = Phase::Move;

    public bool $phase_complete = false;

    public ?array $last_roll = null;

    /** @return Collection<int, PlayerState> */
    public function players(): Collection
    {
        return collect($this->player_ids)->map(fn (int $id) => PlayerState::load($id));
    }

    public function activePlayer(): ?PlayerState
    {
        return $this->active_player_id ? PlayerState::load($this->active_player_id) : null;
    }

    public function hasPlayer(PlayerState|int $player): bool
    {
        if ($player instanceof PlayerState) {
            $player = $player->id;
        }

        return in_array($player, $this->player_ids);
    }
}
