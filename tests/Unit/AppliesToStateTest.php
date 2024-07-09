<?php

use Thunk\Verbs\Attributes\Autodiscovery\AppliesToState;
use Thunk\Verbs\Event;
use Thunk\Verbs\State;

it('can find the state ID property', function () {
    $id = snowflake_id();

    ContactRequestAcknowledged::commit(
        contact_request_id: $id
    );

    $state = ContactRequestState::load($id);

    $this->assertTrue($state->acknowledged);
});

class ContactRequestState extends State
{
    public bool $acknowledged = false;
}

#[AppliesToState(ContactRequestState::class)]
class ContactRequestAcknowledged extends Event
{
    public function __construct(
        public int $contact_request_id
    ) {}

    public function apply(ContactRequestState $state)
    {
        $state->acknowledged = true;
    }
}
