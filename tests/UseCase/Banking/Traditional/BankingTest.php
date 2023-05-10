<?php

namespace Thunk\Verbs\Tests\UseCase\Banking\Traditional;

use Thunk\Verbs\Context;
use Thunk\Verbs\Event;
use Thunk\Verbs\Exceptions\EventNotValidInContext;
use Thunk\Verbs\Facades\Broker;
use Thunk\Verbs\Facades\Bus;
use Thunk\Verbs\Facades\Snowflake;
use Thunk\Verbs\Tests\Fixtures\Models\BankAccount;

it('handles typical a banking implementation', function () {

    // Register projector
    Bus::listen(new AccountProjector());

    // Open account
    $aggregate = AccountAggregateRoot::load(Snowflake::make());

    $aggregate->open(10_000);

    $account = BankAccount::forContext($aggregate->id);

    expect(BankAccount::count())->toBe(1)
        ->and($account->balance)->toBe(10_000);

    // Initial deposit
    $aggregate->deposit(5_000);

    expect($account->refresh()->balance)->toBe(15_000);

    // Attempt overdraft
    expect(fn() => $aggregate->withdraw(100_000))
        ->toThrow(EventNotValidInContext::class)
        ->and($account->refresh()->balance)->toBe(15_000)
        ->and($account->overdraft_attempts)->toBe(1);

    // Working withdrawal
    $aggregate->withdraw(15_000);

    expect($account->refresh()->balance)->toBe(0);

    // Replay
    BankAccount::truncate();
    Broker::replay();

    expect(BankAccount::count())->toBe(1)
        ->and(BankAccount::forContext($aggregate->id))
        ->balance->toBe(0)
        ->overdraft_attempts->toBe(1);
});

class AccountAggregateRoot extends Context
{
    public ?int $balance = null;

    public function open(int $starting_balance)
    {
        $this->fire(new AccountWasOpened($starting_balance));
    }

    public function deposit(int $amount)
    {
        $this->fire(new FundsDeposited($amount));
    }

    public function withdraw(int $amount)
    {
        if ($this->balance < $amount) {
            $this->fire(new AttemptedOverdraft());

            throw new EventNotValidInContext();
        }

        $this->fire(new FundsWithdrawn($amount));
    }

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

class AccountProjector
{
    public function onOpen(AccountWasOpened $event)
    {
        BankAccount::create([
            'context_id' => $event->context_id,
            'balance' => $event->starting_balance,
        ]);
    }

    public function onDeposit(FundsDeposited $event)
    {
        BankAccount::forContext($event->context_id)->increment('balance', $event->amount);
    }

    public function onWithdraw(FundsWithdrawn $event)
    {
        BankAccount::forContext($event->context_id)->decrement('balance', $event->amount);
    }

    public function onOverdraft(AttemptedOverdraft $event)
    {
        BankAccount::forContext($event->context_id)->increment('overdraft_attempts');
    }
}

class AccountWasOpened extends Event
{
    public function __construct(
        public int $starting_balance
    ) {
    }
}

class FundsDeposited extends Event
{
    public function __construct(
        public int $amount
    ) {
    }
}

class FundsWithdrawn extends Event
{
    public function __construct(
        public int $amount
    ) {
    }
}

class AttemptedOverdraft extends Event
{
}
