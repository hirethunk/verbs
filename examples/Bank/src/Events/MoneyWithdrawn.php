<?php

namespace Thunk\Verbs\Examples\Bank\Events;

use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;
use Thunk\Verbs\Examples\Bank\Models\Account;
use Thunk\Verbs\Examples\Bank\States\AccountState;

class MoneyWithdrawn extends Event
{
    #[StateId(AccountState::class)]
    public int $account_id;

    public int $cents = 0;

    public function validate(AccountState $state): bool
    {
        return $state->balance_in_cents >= $this->cents;
    }

    public function apply(AccountState $state): void
    {
        $state->balance_in_cents -= $this->cents;
    }

    public function onFire(): void
    {
        $state = $this->state();

        Account::find($this->account_id)
            ->update([
                'balance_in_cents' => $state->balance_in_cents,
            ]);
    }
}
