<?php

namespace Thunk\Verbs\Tests\UseCase\Banking;

use Thunk\Verbs\Attributes\CreatesContext;
use Thunk\Verbs\Context;
use Thunk\Verbs\Event;
use Thunk\Verbs\Exceptions\EventNotValidInContext;
use Thunk\Verbs\Facades\Broker;
use Thunk\Verbs\HasChildContext;
use Thunk\Verbs\HasParentContext;

beforeEach(fn() => $GLOBALS['test_banking_accounts'] = []);

it('handles typical a banking implementation', function () {
    AccountWasOpened::fire(10_000);

    expect($GLOBALS['test_banking_accounts'])->toHaveCount(1);

    $context_id = array_keys($GLOBALS['test_banking_accounts'])[0];

    expect($GLOBALS['test_banking_accounts'][$context_id]['balance'])->toBe(10_000);

    FundsDeposited::withContext(AccountContext::load($context_id))->fire(5_000);

    expect($GLOBALS['test_banking_accounts'])->toHaveCount(1)
        ->and($GLOBALS['test_banking_accounts'][$context_id]['balance'])->toBe(15_000);
    
    $overdraft_failed = false;
    try {
        FundsWithdrawn::withContext(AccountContext::load($context_id))->fire(100_000);    
    } catch (EventNotValidInContext) {
        $overdraft_failed = true;
    }
    
    expect($overdraft_failed)->toBeTrue()
        ->and($GLOBALS['test_banking_accounts'])->toHaveCount(1)
        ->and($GLOBALS['test_banking_accounts'][$context_id]['balance'])->toBe(15_000)
        ->and($GLOBALS['test_banking_accounts'][$context_id]['overdraft_attempts'])->toBe(1);

    FundsWithdrawn::withContext(AccountContext::load($context_id))->fire(15_000);

    expect($GLOBALS['test_banking_accounts'])->toHaveCount(1)
        ->and($GLOBALS['test_banking_accounts'][$context_id]['balance'])->toBe(0);

    $GLOBALS['test_banking_accounts'] = [];
    
    Broker::replay();

    expect($GLOBALS['test_banking_accounts'])->toHaveCount(1)
        ->and($GLOBALS['test_banking_accounts'][$context_id]['balance'])->toBe(0);
});

class UserContext extends Context
{
    use HasChildContext;
}

class AccountContext extends Context
{
    use HasParentContext;

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
    )
    {
    }

    public function onFire()
    {
        $GLOBALS['test_banking_accounts'][$this->context_id->id()] = [
            'balance' => $this->starting_balance,
        ];
    }
}

class FundsDeposited extends Event
{
    public function __construct(
        public int $amount
    )
    {
    }

    public function onFire()
    {
        $GLOBALS['test_banking_accounts'][$this->context_id->id()]['balance'] += $this->amount;
    }
}

class FundsWithdrawn extends Event
{
    public function __construct(
        public int $amount
    )
    {
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
        $GLOBALS['test_banking_accounts'][$this->context_id->id()]['balance'] -= $this->amount;
    }
}

class AttemptedOverdraft extends Event
{
    public function onFire()
    {
        $GLOBALS['test_banking_accounts'][$this->context_id->id()]['overdraft_attempts'] ??= 0;
        $GLOBALS['test_banking_accounts'][$this->context_id->id()]['overdraft_attempts'] += 1;
    }
}
