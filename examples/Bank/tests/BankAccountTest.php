<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Thunk\Verbs\Examples\Bank\Events\AccountOpened;
use Thunk\Verbs\Examples\Bank\Events\MoneyDeposited;
use Thunk\Verbs\Examples\Bank\Events\MoneyWithdrawn;
use Thunk\Verbs\Examples\Bank\Mail\DepositAvailable;
use Thunk\Verbs\Examples\Bank\Mail\WelcomeEmail;
use Thunk\Verbs\Examples\Bank\Models\Account;
use Thunk\Verbs\Examples\Bank\Models\User;
use Thunk\Verbs\Examples\Bank\States\AccountState;
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
        $open_event = VerbEvent::type(AccountOpened::class)->whereDataContains([
            'user_id' => User::first()->id,
            'initial_deposit_in_cents' => 1000_00,
        ])->first()
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

    expect(
        $deposit_event = VerbEvent::type(MoneyDeposited::class)->whereDataContains([
            'account_id' => $account->id,
            'cents' => 499_99,
        ])->first()
    )->not->toBeNull();

    expect($account->refresh()->balance_in_cents)->toBe(1499_99);

    Mail::assertSent(fn (DepositAvailable $email) => $email->user_id === Auth::id());

    // Now we'll withdraw an amount that we have available to us

    $this->post(
        route('bank.accounts.withdrawals.store', $account),
        [
            'withdrawal_in_cents' => 1399_99,
        ]
    )
        ->assertSessionHasNoErrors()
        ->assertSuccessful();

    expect(
        $withdraw_event = VerbEvent::type(MoneyWithdrawn::class)->whereDataContains([
            'account_id' => $account->id,
            'cents' => 1399_99,
        ])->first()
    )->not->toBeNull();

    expect($account->refresh()->balance_in_cents)->toBe(100_00);

    // Next let's try to withdraw an amount that we don't have

    // $this->withoutExceptionHandling();

    $this->post(
        route('bank.accounts.withdrawals.store', $account),
        [
            'withdrawal_in_cents' => 100_01,
        ]
    )->assertSessionHasErrors();

    expect(
        VerbEvent::type(MoneyWithdrawn::class)->count()
    )->toBe(1);

    expect($account->refresh()->balance_in_cents)->toBe(100_00);

    // Lets assert the events are on the state store in the correct order

    expect(AccountState::load($account->id)->storedEvents())
        ->toHaveCount(3)
        ->sequence(
            fn ($number) => $number->id->toBe($open_event->id),
            fn ($number) => $number->id->toBe($deposit_event->id),
            fn ($number) => $number->id->toBe($withdraw_event->id),
        );

    // Finally, let's replay everything and make sure we get what's expected

    //    Mail::fake();
    //
    //    $account->delete();
    //
    //    Verbs::replay();
    //
    //    $account = Auth::user()->accounts()->sole();
    //
    //    expect($account->balance_in_cents)->toBe(100_00);
    //
    //    Mail::assertNothingOutgoing();
});
