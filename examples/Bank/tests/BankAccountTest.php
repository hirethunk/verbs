<?php

use Thunk\Verbs\Examples\Bank\Models\Account;
use Thunk\Verbs\Examples\Bank\Models\User;

test('a bank account can be opened', function () {
    $this->actingAs(User::factory()->create())
        ->post(
            route('bank.accounts.store'),
            [
                'initial_deposit_in_cents' => 1000_00,
            ]
        )->assertSuccessful();

    expect(Account::count())->toBe(1);
    expect(Account::first()->balance_in_cents)->toBe(1000_00);
});
