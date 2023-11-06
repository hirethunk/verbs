<?php

namespace Thunk\Verbs\Examples\Monopoly\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Session;

class AuthController extends Controller
{
    public function __invoke(Request $request)
    {
        if ($request->has('name')) {
            Session::put('user', ['name' => $request->input('name')]);

            return redirect('/monopoly/games');
        }

        return view('monopoly::login');
    }
}
