<?php

use Glhd\Bits\Snowflake;
use Thunk\Verbs\Examples\Wingspan\Events\DrewCards;
use Thunk\Verbs\Examples\Wingspan\Events\GainedFood;
use Thunk\Verbs\Examples\Wingspan\Events\GameStarted;
use Thunk\Verbs\Examples\Wingspan\Events\LaidEggs;
use Thunk\Verbs\Examples\Wingspan\Events\PlayedBird;
use Thunk\Verbs\Examples\Wingspan\Events\PlayerSetUp;
use Thunk\Verbs\Examples\Wingspan\Events\RoundStarted;
use Thunk\Verbs\Examples\Wingspan\Events\SelectedAsFirstPlayer;
use Thunk\Verbs\Examples\Wingspan\Game\Birds\BaldEagle;
use Thunk\Verbs\Examples\Wingspan\Game\Birds\Crow;
use Thunk\Verbs\Examples\Wingspan\Game\Birds\Goldfinch;
use Thunk\Verbs\Examples\Wingspan\Game\Birds\Hawk;
use Thunk\Verbs\Examples\Wingspan\Game\Birds\Nuthatch;
use Thunk\Verbs\Examples\Wingspan\Game\Food;
use Thunk\Verbs\Examples\Wingspan\States\GameState;
use Thunk\Verbs\Examples\Wingspan\States\RoundState;
use Thunk\Verbs\Exceptions\EventNotValidForCurrentState;

it('can play a game of wingspan', function () {
    // We shouldn't be able to start a game with an invalid number of players
    expect(fn () => GameStarted::fire(player_ids: []))->toThrow(InvalidArgumentException::class)
        ->and(fn () => GameStarted::fire(player_ids: [1, 2, 3, 4, 5, 6]))->toThrow(InvalidArgumentException::class);

    // Game setup
    // ---------------------------------------------------------------------------------------------------------------------------

    $player1_id = Snowflake::make()->id();
    $player2_id = Snowflake::make()->id();

    $start_event = GameStarted::fire(player_ids: [$player1_id, $player2_id]);
    $game_state = $start_event->state(GameState::class);

    $player1_state = $start_event->playerState(0);
    $player2_state = $start_event->playerState(1);

    expect($game_state->started)->toBeTrue()
        ->and($game_state->currentRoundNumber())->toBeNull()
        ->and($game_state->isSetUp())->toBeFalse()
        ->and($player1_state->setup)->toBe(false)
        ->and($player1_state->available_action_cubes)->toBe(8)
        ->and($player2_state->setup)->toBe(false)
        ->and($player2_state->available_action_cubes)->toBe(8)
        ->and(fn () => GameStarted::fire(player_ids: [$player1_id], game_id: $game_state->id))->toThrow(EventNotValidForCurrentState::class);

    // TODO: Deal bird cards and bonus cards to players

    PlayerSetUp::fire(
        player_id: $player1_state->id,
        game_id: $game_state->id,
        bird_cards: [new Hawk(), new Crow()],
        bonus_card: 'bonus1',
        food: [Food::Worm, Food::Wheat, Food::Berries],
    );

    expect($game_state->isSetUp())->toBeFalse()
        ->and($player1_state->setup)->toBe(true)
        ->and($player1_state->bird_cards->is([new Hawk(), new Crow()]))->toBeTrue()
        ->and($player1_state->bonus_cards)->toBe(['bonus1'])
        ->and($player1_state->food->is([Food::Worm, Food::Wheat, Food::Berries]))->toBeTrue()
        ->and($player2_state->setup)->toBe(false);

    PlayerSetUp::fire(
        player_id: $player2_state->id,
        game_id: $game_state->id,
        bird_cards: [new BaldEagle(), new Nuthatch(), new Goldfinch()],
        bonus_card: 'bonus2',
        food: [Food::Mouse, Food::Berries],
    );

    expect($game_state->isSetUp())->toBeFalse()
        ->and($player1_state->setup)->toBe(true)
        ->and($player2_state->setup)->toBe(true)
        ->and($player2_state->bird_cards->is([new BaldEagle(), new Nuthatch(), new Goldfinch()]))->toBeTrue()
        ->and($player2_state->bonus_cards)->toBe(['bonus2'])
        ->and($player2_state->food->is([Food::Mouse, Food::Berries]))->toBeTrue();

    SelectedAsFirstPlayer::fire(
        player_id: $player1_id,
        game_id: $game_state->id,
    );

    expect($player2_state->first_player)->toBe(false)
        ->and($player1_state->first_player)->toBe(true)
        ->and($game_state->isSetUp())->toBeTrue();

    // First Round
    // ---------------------------------------------------------------------------------------------------------------------------

    $round1_state = RoundStarted::fire(game_id: $game_state->id)->state(RoundState::class);

    expect($game_state->currentRoundNumber())->toBe(1)
        ->and($round1_state->active_player_id)->toBe($player1_id);

    PlayedBird::fire(
        player_id: $player1_id,
        round_id: $round1_state->id,
        bird: new Crow(),
        food: [Food::Wheat, Food::Berries], // TODO: You can pay two of anything as a wild card
        // TODO: Which habitat + egg cost
    );

    expect($player1_state->bird_cards->is([new Hawk()]))->toBeTrue()
        ->and($player1_state->food->is([Food::Worm]))->toBeTrue()
        ->and($player1_state->available_action_cubes)->toBe(7)
        ->and($player1_state->board->inGrass()->is([new Crow()]))->toBeTrue();

    GainedFood::fire(
        player_id: $player2_state->id,
        food: Food::Fish, // TODO: We need to know what food is available, plus it may be more than one
    );

    expect($player2_state->food->is([Food::Mouse, Food::Berries, Food::Fish]))->toBeTrue()
        ->and($player2_state->available_action_cubes)->toBe(7);

    DrewCards::fire(
        player_id: $player1_state->id,
        birds: [new Goldfinch(), new Crow()],
    );

    expect($player1_state->bird_cards->is([new Hawk(), new Goldfinch(), new Crow()]))->toBeTrue();

    LaidEggs::fire(
        player_id: $player1_state->id,
        round_id: $round1_state->id,
        birds: [
            $player1_state->board->inGrass()->first(),
            $player1_state->board->inGrass()->first(),
        ],
    );

    expect($player1_state->board->inGrass()->first()->egg_count)->toBe(2);
});
