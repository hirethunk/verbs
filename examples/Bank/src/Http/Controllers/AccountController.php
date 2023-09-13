<?php

namespace Thunk\Verbs\Examples\Bank\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Thunk\Verbs\Examples\Bank\Events\AccountOpened;

class AccountController
{
    public function store(Request $request)
    {
        AccountOpened::fire(
            Auth::user(),
            $request->integer('initial_balance_in_cents')
        );
    }
}
