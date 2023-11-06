<?php

namespace Thunk\Verbs\Examples\Monopoly\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Thunk\Verbs\Examples\Monopoly\Events\Setup\GameStarted;

class GameController extends Controller
{
    public function index()
    {
        return view('monopoly::games.index');
    }

    public function show(int $game_id)
    {
        return $game_id;
    }

    public function store(Request $request)
    {
        $event = GameStarted::fire();

        return redirect("/monopoly/games/{$event->game_id}");
    }
}
