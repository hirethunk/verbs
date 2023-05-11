<?php

namespace Thunk\Verbs\Tests\UseCase\Banking\Encapsulated;

use Thunk\Verbs\Attributes\CreatesContext;
use Thunk\Verbs\Context;
use Thunk\Verbs\Event;
use Thunk\Verbs\Exceptions\EventNotValidInContext;
use Thunk\Verbs\Facades\Broker;
use Thunk\Verbs\Tests\Fixtures\Models\BankAccount;

it('handles typical a banking implementation', function () {

    // Open account
    $event = AccountWasOpened::fire(10_000);

    $account = BankAccount::forContext($event->context_id);

    expect(BankAccount::count())->toBe(1)
        ->and($account->balance)->toBe(10_000);

    // Initial deposit
    $account->context()->fire(new FundsDeposited(5_000));

    expect($account->refresh()->balance)->toBe(15_000);

    // Attempt overdraft
    expect(fn () => $account->context()->fire(new FundsWithdrawn(100_000)))
        ->toThrow(EventNotValidInContext::class)
        ->and($account->refresh()->balance)->toBe(15_000)
        ->and($account->overdraft_attempts)->toBe(1);

    // Working withdrawal
    $account->context()->fire(new FundsWithdrawn(15_000));

    expect($account->refresh()->balance)->toBe(0);

    // Replay
    BankAccount::truncate();
    Broker::replay();

    expect(BankAccount::count())->toBe(1)
        ->and(BankAccount::forContext($event->context_id))
        ->balance->toBe(0)
        ->overdraft_attempts->toBe(1);
});

class AccountContext extends Context
{
    public ?int $balance = null;

    public function applyOpen(AccountWasOpened $event)
    {
        $this->balance = $event->starting_balance;
    }

    public function applyDeposit(FundsDeposited $event)
    {
        $this->balance += $event->amount;
    }

    public function applyWithdrawal(FundsWithdrawn $event)
    {
        $this->balance -= $event->amount;
    }
}

#[CreatesContext(AccountContext::class)]
class AccountWasOpened extends Event
{
    public function __construct(
        public int $starting_balance
    ) {
    }

    public function onFire()
    {
        BankAccount::create([
            'context_id' => $this->context_id,
            'balance' => $this->starting_balance,
        ]);
    }
}

class FundsDeposited extends Event
{
    public function __construct(
        public int $amount
    ) {
    }

    public function onFire()
    {
        BankAccount::forContext($this->context_id)->increment('balance', $this->amount);
    }
}

class FundsWithdrawn extends Event
{
    public function __construct(
        public int $amount
    ) {
    }

    public function rules(): array
    {
        return [
            'balance' => "gte:{$this->amount}",
        ];
    }

    public function failedValidation(AccountContext $context)
    {
        AttemptedOverdraft::withContext($context)->fire();
    }

    public function onFire()
    {
        BankAccount::forContext($this->context_id)->decrement('balance', $this->amount);
    }
}

class AttemptedOverdraft extends Event
{
    public function onFire()
    {
        BankAccount::forContext($this->context_id)->increment('overdraft_attempts');
    }
}
