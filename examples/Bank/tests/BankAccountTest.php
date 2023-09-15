<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Thunk\Verbs\Examples\Bank\Events\AccountOpened;
use Thunk\Verbs\Examples\Bank\Mail\DepositAvailable;
use Thunk\Verbs\Examples\Bank\Mail\WelcomeEmail;
use Thunk\Verbs\Examples\Bank\Models\User;
use Thunk\Verbs\Facades\Verbs;
use Thunk\Verbs\Models\VerbEvent;

test('a bank account can be opened and interacted with', function () {
    Mail::fake();

    $this->actingAs(User::factory()->create());

    // First we'll open the account

    $this->post(
        route('bank.accounts.store'),
        [
            'initial_deposit_in_cents' => 1000_00,
        ]
    )->assertSuccessful();

    expect(
        VerbEvent::type(AccountOpened::class)->whereDataContains([
            'initial_deposit_in_cents' => 1000_00,
            'user_id' => User::first()->id,
        ])
    )->not->toBeNull();

    $account = Auth::user()->accounts()->sole();
    expect($account->balance_in_cents)->toBe(1000_00);

    Mail::assertSent(fn (WelcomeEmail $email) => $email->user_id === Auth::id());

    // Then we'll deposit some money

    $this->post(
        route('bank.accounts.deposits.store', $account),
        [
            'deposit_in_cents' => 499_99,
        ]
    )->assertSuccessful();

    expect($account->refresh()->balance_in_cents)->toBe(1499_99);

    Mail::assertSent(fn (DepositAvailable $email) => $email->user_id === Auth::id());

    // Now we'll withdraw an amount that we have available to us

    $this->post(
        route('bank.accounts.withdrawals.store', $account),
        [
            'withdrawal_in_cents' => 1399_99,
        ]
    )->assertSuccessful();

    expect($account->refresh()->balance_in_cents)->toBe(100_00);

    // Next let's try to withdraw an amount that we don't have

    $this->post(
        route('bank.accounts.withdrawals.store', $account),
        [
            'withdrawal_in_cents' => 100_01,
        ]
    )->assertSessionHasErrors();

    expect($account->refresh()->balance_in_cents)->toBe(100_00);

    // Finally, let's replay everything and make sure we get what's expected

    Mail::fake();

    $account->delete();

    Verbs::replay();

    $account = Auth::user()->accounts()->sole();

    expect($account->balance_in_cents)->toBe(100_00);

    Mail::assertNothingOutgoing();
});
