<?php

use Illuminate\Support\Facades\Mail;
use Thunk\Verbs\Examples\Bank\Events\AccountOpened;
use Thunk\Verbs\Examples\Bank\Mail\WelcomeEmail;
use Thunk\Verbs\Examples\Bank\Models\Account;
use Thunk\Verbs\Examples\Bank\Models\User;
use Thunk\Verbs\VerbEvent;

test('a bank account can be opened', function () {
    Mail::fake();

    $this->actingAs(User::factory()->create())
        ->withoutExceptionHandling()
        ->post(
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
    
    expect(Account::count())->toBe(1);
    expect(Account::first()->balance_in_cents)->toBe(1000_00);

    expect(Mail::sent(WelcomeEmail::class)->count())->toBe(1);
});
