<?php

use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;
use Thunk\Verbs\Facades\Verbs;
use Thunk\Verbs\Models\VerbEvent;
use Thunk\Verbs\State;

it('does not load the incorrect state in a nested fired event', function () {
    SubscriptionAdded::fire(
        organisation_id: '1',
        provider: 'stripe',
        source_order_line_item_id: '5',
    );

    Verbs::commit();

    expect($events = VerbEvent::all())
        ->toHaveCount(2)
        ->and($events[0])
        ->type->toBe(SubscriptionAdded::class)
        ->and($events[1])
        ->type->toBe(GrantAccountCredit::class);
});

class SubscriptionAdded extends Event
{
    #[StateId(SubscriptionState::class)]
    public string $organisation_id;

    public function apply(SubscriptionState $state): void
    {
        $state->is_first_subscription = is_null($state->is_first_subscription);
    }

    public function handle()
    {
        GrantAccountCredit::fire(
            organisation_id: $this->organisation_id,
            credit_amount_cents: 50000
        );
    }
}

class SubscriptionState extends State
{
    public ?bool $is_first_subscription = null;
}

class GrantAccountCredit extends Event
{
    public function __construct(
        #[StateId(AccountCreditState::class)]
        public string $organisation_id,
        public int $credit_amount_cents,
    ) {}

    public function apply(AccountCreditState $state): void
    {
        $state->balance_in_cents += $this->credit_amount_cents;
    }
}

class AccountCreditState extends State
{
    public int $balance_in_cents = 0;
}
