<?php

use Thunk\Verbs\Examples\Wingspan\Events\GameStarted;
use Thunk\Verbs\Examples\Wingspan\Events\PlayerSetUp;
use Thunk\Verbs\Examples\Wingspan\Events\RoundStarted;
use Thunk\Verbs\Examples\Wingspan\Events\SelectedAsFirstPlayer;
use Thunk\Verbs\Examples\Wingspan\States\GameState;
use Thunk\Verbs\Exceptions\EventNotValidForCurrentState;

it('can play a game of wingspan', function () {
    // We shouldn't be able to start a game with an invalid number of players
    expect(fn () => GameStarted::fire(players: 0))->toThrow(InvalidArgumentException::class)
        ->and(fn () => GameStarted::fire(players: 6))->toThrow(InvalidArgumentException::class);

    // Game setup
    // ---------------------------------------------------------------------------------------------------------------------------

    $start_event = GameStarted::fire(players: 2);
    $game_state = $start_event->state(GameState::class);

    $player1_state = $start_event->playerState(0);
    $player2_state = $start_event->playerState(1);

    expect($game_state->started)->toBeTrue()
        ->and($game_state->round)->toBe(0)
        ->and($game_state->setup_count)->toBe(0)
        ->and($player1_state->setup)->toBe(false)
        ->and($player1_state->available_action_cubes)->toBe(8)
        ->and($player2_state->setup)->toBe(false)
        ->and($player2_state->available_action_cubes)->toBe(8)
        ->and(fn () => GameStarted::fire(players: 2, game_id: $game_state->id))->toThrow(EventNotValidForCurrentState::class);

    PlayerSetUp::fire(
        player_id: $player1_state->id,
        game_id: $game_state->id,
        bird_cards: ['hawk', 'crow'],
        bonus_card: 'bonus1',
        food: ['worm', 'wheat', 'fish']
    );

    expect($game_state->setup_count)->toBe(1)
        ->and($player1_state->setup)->toBe(true)
        ->and($player2_state->setup)->toBe(false);

    PlayerSetUp::fire(
        player_id: $player2_state->id,
        game_id: $game_state->id,
        bird_cards: ['bald eagle', 'nuthatch', 'finch'],
        bonus_card: 'bonus2',
        food: ['mouse', 'berries']
    );

    expect($game_state->setup_count)->toBe(2)
        ->and($player1_state->setup)->toBe(true)
        ->and($player2_state->setup)->toBe(true);

    SelectedAsFirstPlayer::fire(
        player_id: $player2_state->id,
        game_id: $game_state->id,
    );

    expect($player1_state->first_player)->toBe(false)
        ->and($player2_state->first_player)->toBe(true)
        ->and($game_state->isSetUp())->toBeTrue();

    // First Round
    // ---------------------------------------------------------------------------------------------------------------------------

    RoundStarted::fire(game_id: $game_state->id);

    expect($game_state->round)->toBe(1);
});
