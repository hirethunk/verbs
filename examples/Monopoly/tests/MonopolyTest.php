<?php

use Thunk\Verbs\Examples\Monopoly\Events\Gameplay\EndedTurn;
use Thunk\Verbs\Examples\Monopoly\Events\Gameplay\PaidRent;
use Thunk\Verbs\Examples\Monopoly\Events\Gameplay\PlayerMoved;
use Thunk\Verbs\Examples\Monopoly\Events\Gameplay\PurchasedProperty;
use Thunk\Verbs\Examples\Monopoly\Events\Gameplay\RolledDice;
use Thunk\Verbs\Examples\Monopoly\Events\Setup\FirstPlayerSelected;
use Thunk\Verbs\Examples\Monopoly\Events\Setup\GameStarted;
use Thunk\Verbs\Examples\Monopoly\Events\Setup\PlayerJoinedGame;
use Thunk\Verbs\Examples\Monopoly\Game\Spaces\Go;
use Thunk\Verbs\Examples\Monopoly\Game\Spaces\Properties\BalticAvenue;
use Thunk\Verbs\Examples\Monopoly\Game\Spaces\Properties\OrientalAvenue;
use Thunk\Verbs\Examples\Monopoly\Game\Token;
use Thunk\Verbs\Examples\Monopoly\States\GameState;
use Thunk\Verbs\Examples\Monopoly\States\PlayerState;
use Thunk\Verbs\Examples\Monopoly\Support\MoneyNormalizer;
use Thunk\Verbs\Exceptions\EventNotValidForCurrentState;
use Thunk\Verbs\Facades\Verbs;
use Thunk\Verbs\Models\VerbSnapshot;

beforeEach(function () {
    $normalizers = array_merge([MoneyNormalizer::class], config('verbs.normalizers'));
    config()->set('verbs.normalizers', $normalizers);
});

it('can play a game of Monopoly', function () {

    // Game setup
    // ---------------------------------------------------------------------------------------------------------------------------

    $player1_id = snowflake_id();
    $player2_id = snowflake_id();

    $game_state = verb(new GameStarted)->state(GameState::class);

    expect($game_state->started)->toBeTrue()
        ->and($game_state->board->spaces->count())->toBe(40)
        ->and($game_state->active_player_id)->toBeNull()
        ->and(fn () => GameStarted::fire(game_id: $game_state->id))->toThrow(EventNotValidForCurrentState::class);

    verb(new PlayerJoinedGame(
        game_id: $game_state->id,
        player_id: $player1_id,
        token: Token::Battleship,
    ));

    $player1 = PlayerState::load($player1_id);

    expect($player1->token)->toBe(Token::Battleship)
        ->and($player1->location)->toBe(Go::instance())
        ->and($player1->money)->toBeMoney(1500, 'USD')
        ->and($player1->deeds)->toBeEmpty()
        ->and($game_state->active_player_id)->toBeNull();

    verb(new PlayerJoinedGame(
        game_id: $game_state->id,
        player_id: $player2_id,
        token: Token::TopHat,
    ));

    $player2 = PlayerState::load($player2_id);

    expect($player2->token)->toBe(Token::TopHat)
        ->and($player2->location)->toBe(Go::instance())
        ->and($player2->money)->toBeMoney(1500, 'USD')
        ->and($player2->deeds)->toBeEmpty()
        ->and($game_state->active_player_id)->toBeNull();

    verb(new FirstPlayerSelected($game_state->id, $player1_id));

    expect($game_state->active_player_id)->toBe($player1_id);

    // We'll commit what we have so far and make sure that the state in the database
    // matches what we've got loaded into memory.

    Verbs::commit();

    $snapshot_state = VerbSnapshot::query()
        ->firstWhere('type', GameState::class)
        ->state();

    expect($snapshot_state->started)->toBeTrue()
        ->and(serialize($snapshot_state->bank))->toBe(serialize($game_state->bank))
        ->and(serialize($snapshot_state->board))->toBe(serialize($game_state->board))
        ->and($snapshot_state->active_player_id)->toBe($game_state->active_player_id);

    // Player 1's first move
    // ---------------------------------------------------------------------------------------------------------------------------

    verb(new RolledDice(
        game_id: $game_state->id,
        player_id: $player1_id,
        dice: [1, 2],
    ));

    verb(new PlayerMoved(
        game_id: $game_state->id,
        player_id: $player1_id,
        to: BalticAvenue::instance(),
    ));

    expect($player1->location)->toBe(BalticAvenue::instance());

    verb(new PurchasedProperty(
        game_id: $game_state->id,
        player_id: $player1_id,
        property: BalticAvenue::instance(),
    ));

    expect($player1->deeds)->toHaveCount(1)
        ->and($player1->deeds->first())->toBe(BalticAvenue::instance())
        ->and($player1->money)->toBeMoney(1440, 'USD')
        ->and($game_state->bank->hasDeed(BalticAvenue::instance()))->toBeFalse();

    verb(new EndedTurn(game_id: $game_state->id, player_id: $player1_id));

    expect($game_state->active_player_id)->toBe($player2_id);

    // Player 2's first move
    // ---------------------------------------------------------------------------------------------------------------------------

    verb(new RolledDice(
        game_id: $game_state->id,
        player_id: $player2_id,
        dice: [3, 3],
    ));

    verb(new PlayerMoved(
        game_id: $game_state->id,
        player_id: $player2_id,
        to: OrientalAvenue::instance(),
    ));

    expect($player2->location)->toBe(OrientalAvenue::instance());

    verb(new PurchasedProperty(
        game_id: $game_state->id,
        player_id: $player2_id,
        property: OrientalAvenue::instance(),
    ));

    expect($player2->deeds)->toHaveCount(1)
        ->and($player2->deeds->first())->toBe(OrientalAvenue::instance())
        ->and($player2->money)->toBeMoney(1400, 'USD')
        ->and($game_state->bank->hasDeed(OrientalAvenue::instance()))->toBeFalse();

    verb(new EndedTurn(game_id: $game_state->id, player_id: $player2_id));

    expect($game_state->active_player_id)->toBe($player1_id);

    // Player 1's second move
    // ---------------------------------------------------------------------------------------------------------------------------

    verb(new RolledDice(
        game_id: $game_state->id,
        player_id: $player1_id,
        dice: [1, 2],
    ));

    verb(new PlayerMoved(
        game_id: $game_state->id,
        player_id: $player1_id,
        to: OrientalAvenue::instance(),
    ));

    expect($player1->location)->toBe(OrientalAvenue::instance());

    verb(new PaidRent(
        game_id: $game_state->id,
        player_id: $player1_id,
        property: OrientalAvenue::instance(),
    ));

    expect($player1->money)->toBeMoney(1434, 'USD')
        ->and($player2->money)->toBeMoney(1406, 'USD');

    verb(new EndedTurn(game_id: $game_state->id, player_id: $player1_id));

    expect($game_state->active_player_id)->toBe($player2_id);
});
