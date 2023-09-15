<?php

namespace Thunk\Verbs\Examples\Bank\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Thunk\Verbs\Examples\Bank\Events\AccountOpened;
use Thunk\Verbs\Examples\Bank\Events\MoneyDeposited;
use Thunk\Verbs\Examples\Bank\Events\MoneyWithdrawn;
use Thunk\Verbs\Examples\Bank\Models\Account;

class AccountController
{
    public function store(Request $request)
    {
        AccountOpened::fire(
            user_id: Auth::id(),
            initial_deposit_in_cents: $request->integer('initial_deposit_in_cents'),
        );
    }

    public function deposit(Request $request, Account $account)
    {
        MoneyDeposited::fire(
            account_id: $account->id,
            cents: $request->integer('deposit_in_cents')
        );
    }

    public function withdraw(Request $request, Account $account)
    {
        MoneyWithdrawn::fire(
            account_id: $account->id,
            cents: $request->integer('withdrawal_in_cents')
        );
    }
}
