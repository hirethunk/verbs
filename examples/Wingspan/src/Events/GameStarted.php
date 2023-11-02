<?php

namespace Thunk\Verbs\Examples\Wingspan\Events;

use Glhd\Bits\Snowflake;
use InvalidArgumentException;
use Thunk\Verbs\Event;
use Thunk\Verbs\Examples\Wingspan\States\GameState;
use Thunk\Verbs\Examples\Wingspan\States\PlayerState;
use Thunk\Verbs\State;
use Thunk\Verbs\Support\StateCollection;

class GameStarted extends Event
{
    public ?int $game_id = null;

    public function __construct(
        public int $players,
        public array $player_ids = [],
    ) {
        if ($this->players < 1 || $this->players > 5) {
            throw new InvalidArgumentException('Wingspan can be played with 1-5 players.');
        }

        if (count($this->player_ids) > 0 && count($this->player_ids) !== $this->players) {
            throw new InvalidArgumentException('If you pass player IDs you must pass the same number as players.');
        }
    }

    public function states(): StateCollection
    {
        $this->game_id ??= Snowflake::make()->id();

        while (count($this->player_ids) < $this->players) {
            $this->player_ids[] = Snowflake::make()->id();
        }

        return StateCollection::make([GameState::load($this->game_id)])
            ->merge(collect($this->player_ids)->map(fn ($id) => PlayerState::load($id)));
    }

    public function playerState(int $index = null): PlayerState
    {
        return $index
            ? $this->states()->filter(fn (State $state) => $state instanceof PlayerState)->values()->get($index)
            : $this->states()->firstWhere(fn (State $state) => $state instanceof PlayerState);
    }

    public function validate(GameState $state): bool
    {
        return ! $state->started;
    }

    public function applyToGame(GameState $state)
    {
        // TODO: It might be nice to be able to combine these apply methods into one

        $state->started = true;
        $state->players = $this->players;
    }

    public function applyToPlayers(PlayerState $state)
    {
        $state->available_action_cubes = 8;
    }
}
