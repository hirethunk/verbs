<?php

namespace Thunk\Verbs\Examples\Monopoly\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Session;
use Thunk\Verbs\Examples\Monopoly\Events\Setup\GameStarted;
use Thunk\Verbs\Examples\Monopoly\Game\Token;
use Thunk\Verbs\Examples\Monopoly\States\GameState;

class GameController extends Controller
{
    public function index()
    {
        return view('monopoly::games.index');
    }

    public function show(Request $request, int $game_id)
    {
        if (! $request->session()->has('user')) {
            return redirect('/monopoly/login');
        }

        return view('monopoly::games.show', [
            'game' => GameState::load($game_id),
            'player_id' => $request->session()->get('user.current_player_id'),
            'tokens' => Token::cases(),
        ]);
    }

    public function store(Request $request)
    {
        $event = GameStarted::fire();

        return redirect("/monopoly/games/{$event->game_id}");
    }
}
