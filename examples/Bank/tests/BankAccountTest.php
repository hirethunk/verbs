<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Thunk\Verbs\Examples\Bank\Events\AccountOpened;
use Thunk\Verbs\Examples\Bank\Events\MoneyDeposited;
use Thunk\Verbs\Examples\Bank\Events\MoneyWithdrawn;
use Thunk\Verbs\Examples\Bank\Mail\DepositAvailable;
use Thunk\Verbs\Examples\Bank\Mail\WelcomeEmail;
use Thunk\Verbs\Examples\Bank\Models\User;
use Thunk\Verbs\Examples\Bank\States\AccountState;
use Thunk\Verbs\Facades\Verbs;
use Thunk\Verbs\Lifecycle\StateManager;
use Thunk\Verbs\Models\VerbEvent;

test('a bank account can be opened and interacted with', function () {
    Mail::fake();

    // $this->withoutExceptionHandling();

    $this->actingAs(User::factory()->create());

    // First we'll open the account

    $this->post(route('bank.accounts.store'), ['initial_deposit_in_cents' => 1000_00])
        ->assertSuccessful();

    $open_event = VerbEvent::type(AccountOpened::class)->sole();
    expect($open_event->data['user_id'])->toBe(User::first()->id)
        ->and($open_event->data['initial_deposit_in_cents'])->toBe(1000_00);

    $account = Auth::user()->accounts()->sole();
    expect($account->balance_in_cents)->toBe(1000_00);

    Mail::assertSent(fn (WelcomeEmail $email) => $email->user_id === Auth::id());

    // Then we'll deposit some money

    $this->post(route('bank.accounts.deposits.store', $account), ['deposit_in_cents' => 499_99])
        ->assertSuccessful();

    $deposit_event = VerbEvent::type(MoneyDeposited::class)->sole();

    expect($deposit_event->data['account_id'])->toBe($account->id)
        ->and($deposit_event->data['cents'])->toBe(499_99)
        ->and($account->refresh()->balance_in_cents)->toBe(1499_99);

    Mail::assertSent(fn (DepositAvailable $email) => $email->user_id === Auth::id());

    // Now we'll withdraw an amount that we have available to us

    $this->post(route('bank.accounts.withdrawals.store', $account), ['withdrawal_in_cents' => 1399_99])
        ->assertSessionHasNoErrors()
        ->assertSuccessful();

    $withdraw_event = VerbEvent::type(MoneyWithdrawn::class)->sole();

    expect($withdraw_event->data['account_id'])->toBe($account->id)
        ->and($withdraw_event->data['cents'])->toBe(1399_99)
        ->and($account->refresh()->balance_in_cents)->toBe(100_00);

    // Next let's try to withdraw an amount that we don't have

    $this->post(route('bank.accounts.withdrawals.store', $account), ['withdrawal_in_cents' => 100_01])
        ->assertSessionHasErrors();

    expect(VerbEvent::type(MoneyWithdrawn::class)->count())->toBe(1)
        ->and($account->refresh()->balance_in_cents)->toBe(100_00);

    // Let's assert the events are on the state store in the correct order

    expect(AccountState::load($account->id)->storedEvents())
        ->toHaveCount(3)
        ->sequence(
            fn ($number) => $number->id->toBe($open_event->id),
            fn ($number) => $number->id->toBe($deposit_event->id),
            fn ($number) => $number->id->toBe($withdraw_event->id),
        );

    // Finally, let's replay everything and make sure we get what's expected

    Mail::fake();

    $account->delete();

    Verbs::replay();

    $account = Auth::user()->accounts()->sole();
    $account_state = AccountState::load($account->id);

    expect($account->balance_in_cents)->toBe(100_00)
        ->and($account_state->balance_in_cents)->toBe(100_00);

    Mail::assertNothingOutgoing();

    // We'll also confirm that the state is correctly loaded without snapshots

    app(StateManager::class)->reset(include_storage: true);

    $account_state = AccountState::load($account->id);
    expect($account_state->balance_in_cents)->toBe(100_00);
});
