<?php

namespace Thunk\Verbs\Examples\Monopoly\States;

use Illuminate\Support\Collection;
use Thunk\Verbs\State;

class GameState extends State
{
    public bool $started = false;

    public ?int $first_player_id = null;

    public ?int $current_round_id = null;

    public array $player_ids = [];

    protected ?Collection $players = null;

    public function isSetUp(): bool
    {
        return $this->started
            && $this->players()->where('setup', false)->isEmpty()
            && $this->first_player_id;
    }

    public function currentRound(): ?RoundState
    {
        return $this->current_round_id ? RoundState::load($this->current_round_id) : null;
    }

    public function currentRoundNumber(): ?int
    {
        return $this->currentRound()?->number;
    }

    /** @return Collection<int, PlayerState> */
    public function players(): Collection
    {
        return collect($this->player_ids)->map(fn (int $id) => PlayerState::load($id));
    }
}
