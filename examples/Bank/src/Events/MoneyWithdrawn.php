<?php

namespace Thunk\Verbs\Examples\Bank\Events;

use Exception;
use Illuminate\Support\Facades\Mail;
use Thunk\Verbs\Examples\Bank\Models\Account;
use Thunk\Verbs\Examples\Bank\States\AccountState;
use Thunk\Verbs\Facades\Snowflake;

class MoneyWithdrawn
{
	public AccountState $account_state;
	
	public function __construct(
		public Snowflake $account_id,
		public int $cents = 0,
	) {
		$this->account_state = AccountState::load($this->account_id);
	}
	
	public function validate(): bool
	{
		return $this->account_state->balance_in_cents >= $this->cents;
	}
	
	public function apply(): void
	{
		$this->account_state->balance_in_cents -= $this->cents;
	}
	
	public function onFire(): void
	{
		Account::find($this->account_state->id())
			->update([
				'balance' => $this->account_state->balance_in_cents,
			]);
	}
}
