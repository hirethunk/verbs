<?php

namespace Thunk\Verbs\Examples\Bank\Events;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;
use Thunk\Verbs\Examples\Bank\Mail\WelcomeEmail;
use Thunk\Verbs\Examples\Bank\Models\Account;
use Thunk\Verbs\Examples\Bank\States\AccountState;
use Thunk\Verbs\Facades\Verbs;

class AccountOpened extends Event
{
    #[StateId(AccountState::class)]
    public ?int $account_id = null;

    public int $user_id;

    public int $initial_deposit_in_cents = 0;

    public function validate(AccountState $state): bool
    {
        return ($state->balance_in_cents + $this->initial_deposit_in_cents) > 0;
    }

    public function apply(AccountState $state)
    {
        $state->balance_in_cents = $this->initial_deposit_in_cents;
    }

    public function handle()
    {
        Account::create([
            'id' => $this->account_id,
            'user_id' => $this->user_id,
            'balance_in_cents' => $this->initial_deposit_in_cents,
        ]);

        Verbs::unlessReplaying(fn () => Mail::send(new WelcomeEmail($this->user_id)));
    }

    public static function migrate()
    {
        return [
            1 => fn (Collection $v0) => $v0->except('user_id'),
            2 => fn (Collection $v1) => $v1->merge(['email' => 'default@email.gov']),
        ];
    }
}
