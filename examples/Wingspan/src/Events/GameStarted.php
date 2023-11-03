<?php

namespace Thunk\Verbs\Examples\Wingspan\Events;

use Illuminate\Support\Collection;
use InvalidArgumentException;
use Thunk\Verbs\Attributes\Autodiscovery\AppliesToState;
use Thunk\Verbs\Event;
use Thunk\Verbs\Examples\Wingspan\Game\Birds\BirdCollection;
use Thunk\Verbs\Examples\Wingspan\Game\Board;
use Thunk\Verbs\Examples\Wingspan\States\GameState;
use Thunk\Verbs\Examples\Wingspan\States\PlayerState;
use Thunk\Verbs\State;
use Thunk\Verbs\Support\StateCollection;

#[AppliesToState(GameState::class)]
class GameStarted extends Event
{
    public ?int $game_id = null;

    public Collection $player_ids;

    public function __construct(
        array $player_ids,
    ) {
        $this->player_ids = Collection::make($player_ids);

        if ($this->player_ids->count() < 1 || $this->player_ids->count() > 5) {
            throw new InvalidArgumentException('Wingspan can be played with 1-5 players.');
        }
    }

    public function states(): StateCollection
    {
        $states = parent::states();

        foreach ($this->player_ids as $player_id) {
            $states->push(PlayerState::load($player_id));
        }

        return $states;
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
        $state->started = true;
        $state->player_ids = $this->player_ids->all();
    }

    public function applyToPlayers(PlayerState $state)
    {
        $state->available_action_cubes = 8;
        $state->board = new Board();
    }
}
