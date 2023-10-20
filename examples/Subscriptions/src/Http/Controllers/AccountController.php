<?php

namespace Thunk\Verbs\Examples\Subscriptions\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Thunk\Verbs\Examples\Subscriptions\Events\AccountOpened;
use Thunk\Verbs\Examples\Subscriptions\Events\MoneyDeposited;
use Thunk\Verbs\Examples\Subscriptions\Models\Account;

class AccountController
{
    public function store(Request $request)
    {
        AccountOpened::fire(
            Auth::id(),
            $request->integer('initial_deposit_in_cents')
        );
    }

    public function deposit(Request $request, Account $account)
    {
        MoneyDeposited::fire(
            $account->id,
            $request->integer('deposit_in_cents')
        );
    }
}
