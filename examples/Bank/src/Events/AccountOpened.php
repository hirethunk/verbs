<?php

namespace Thunk\Verbs\Examples\Bank\Events;

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Mail;
use Thunk\Verbs\Examples\Bank\Models\Account;
use Thunk\Verbs\Examples\Bank\States\AccountState;
use Thunk\Verbs\Facades\Snowflake;

class AccountOpened
{
    public AccountState $account_state;

    public function __construct(
        public Snowflake $user_id,
        public int $initial_deposit_in_cents = 0,
    ) {
        $this->account_state = AccountState::initialize();
    }

    public function apply()
    {
        $this->account_state->balance_in_cents = $this->initial_deposit_in_cents;
    }

    public function onFire()
    {
        Account::create([
            'id' => $this->account_state->id(),
            'user_id' => $this->user_id, // User::find($this->user_id)->getKey(),
            'balance' => $this->initial_deposit_in_cents,
        ]);
    }

    public function onCommit()
    {
        Mail::send('your-account-is-ready');
    }
}
