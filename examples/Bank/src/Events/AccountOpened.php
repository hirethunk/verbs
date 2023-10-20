<?php

namespace Thunk\Verbs\Examples\Bank\Events;

use Glhd\Bits\Snowflake;
use Illuminate\Support\Facades\Mail;
use Throwable;
use Thunk\Verbs\Event;
use Thunk\Verbs\Examples\Bank\Mail\WelcomeEmail;
use Thunk\Verbs\Examples\Bank\Models\Account;
use Thunk\Verbs\Examples\Bank\States\AccountState;

class AccountOpened extends Event
{
    public ?int $account_id = null;

    public int $user_id;

    public int $initial_deposit_in_cents = 0;

    public function states(): array
    {
        try {
            // TODO: This should eventually be handled by magic for you
            $this->account_id ??= Snowflake::make()->id();

            return [AccountState::load($this->account_id)];
        } catch (Throwable $exception) {
            dd($exception);
        }
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
            'id' => $this->account_id,
            'user_id' => $this->user_id,
            'balance_in_cents' => $this->initial_deposit_in_cents,
        ]);
    }

    public function onCommit()
    {
        Mail::send(new WelcomeEmail($this->user_id));
    }
}
