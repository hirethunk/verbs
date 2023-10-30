<?php

namespace Thunk\Verbs\Examples\Bank\Events;

use Illuminate\Support\Facades\Mail;
use Thunk\Verbs\Attributes\Identifies;
use Thunk\Verbs\Event;
use Thunk\Verbs\Examples\Bank\Mail\DepositAvailable;
use Thunk\Verbs\Examples\Bank\Models\Account;
use Thunk\Verbs\Examples\Bank\States\AccountState;

class MoneyDeposited extends Event
{
    public function __construct(
        #[Identifies(AccountState::class)]
        public int $account_id,
        public int $cents = 0,
    ) {
    }

    public function apply(AccountState $state)
    {
        $state->balance_in_cents += $this->cents;
    }

    public function onFire()
    {
        [$state] = $this->states();

        Account::find($this->account_id)
            ->update([
                'balance_in_cents' => $state->balance_in_cents,
            ]);
    }

    public function onCommit()
    {
        Mail::send(new DepositAvailable(Account::find($this->account_id)->user_id));
    }
}
