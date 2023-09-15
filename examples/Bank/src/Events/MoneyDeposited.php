<?php

namespace Thunk\Verbs\Examples\Bank\Events;

use Illuminate\Support\Facades\Mail;
use Thunk\Verbs\Event;
use Thunk\Verbs\Examples\Bank\Mail\DepositAvailable;
use Thunk\Verbs\Examples\Bank\Mail\WelcomeEmail;
use Thunk\Verbs\Examples\Bank\Models\Account;
use Thunk\Verbs\Examples\Bank\States\AccountState;
use Thunk\Verbs\Facades\Snowflake;

class MoneyDeposited extends Event
{
    public AccountState $account_state;

    public function __construct(
        public int $account_id,
        public int $cents = 0,
    ) {
        $this->account_state = AccountState::load($this->account_id);
    }

    public function apply(AccountState $state)
    {
        $state->balance_in_cents += $this->cents;
    }

    public function onFire()
    {
        Account::find($this->account_id)
            ->update([
                'balance_in_cents' => $this->account_state->balance_in_cents,
            ]);
    }

    public function onCommit()
    {
        Mail::send(new DepositAvailable(Account::find($this->account_id)->user_id));
    }
}
