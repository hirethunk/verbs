<?php

namespace Thunk\Verbs\Examples\Bank\Events;

use Illuminate\Support\Facades\Mail;
use Thunk\Verbs\Event;
use Thunk\Verbs\Examples\Bank\Mail\WelcomeEmail;
use Thunk\Verbs\Examples\Bank\Models\Account;
use Thunk\Verbs\Examples\Bank\States\AccountState;

class AccountOpened extends Event
{
    public AccountState $account_state;

    public function __construct(
        public int $user_id,
        public int $initial_deposit_in_cents = 0,
    ) {
        $this->account_state = AccountState::initialize();
    }

    public function validate(AccountState $state): bool
    {
        return ($state->balance_in_cents + $this->initial_deposit_in_cents) > 0;
    }

    public function apply(AccountState $state)
    {
        $state->balance_in_cents = $this->initial_deposit_in_cents;
    }

    public function onFire()
    {
        Account::create([
            'id' => $this->account_state->id,
            'user_id' => $this->user_id, // User::find($this->user_id)->getKey(),
            'balance_in_cents' => $this->initial_deposit_in_cents,
        ]);
    }

    public function onCommit()
    {
        Mail::send(new WelcomeEmail($this->user_id));
    }
}
