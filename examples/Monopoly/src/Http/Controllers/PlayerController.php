<?php

namespace Thunk\Verbs\Examples\Monopoly\Http\Controllers;

use Glhd\Bits\Snowflake;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Session;
use Thunk\Verbs\Examples\Monopoly\Events\Setup\PlayerJoinedGame;
use Thunk\Verbs\Examples\Monopoly\Game\Token;

class PlayerController extends Controller
{
    public function store(Request $request, int $game_id)
    {
        $player_id = Snowflake::make()->id();

        Session::put('user.current_player_id', $player_id);

        event(new PlayerJoinedGame(
            game_id: $game_id,
            player_id: $player_id,
            token: Token::from($request->input('token')),
        ));

        return redirect("/monopoly/games/{$game_id}");
    }
}
