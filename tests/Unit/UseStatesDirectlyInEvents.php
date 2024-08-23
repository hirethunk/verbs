<?php

use Thunk\Verbs\Event;
use Thunk\Verbs\State;

it('supports using states directly in events', function () {
    $contact_request = ContactRequestState::new();

    ContactRequestAcknowledged::commit(
        contact_request: $contact_request
    );

    $this->assertTrue($contact_request->acknowledged);
});

class ContactRequestState extends State
{
    public bool $acknowledged = false;
}

class ContactRequestAcknowledged extends Event
{
    public function __construct(
        public ContactRequestState $contact_request
    ) {}

    public function apply()
    {
        $this->contact_request->acknowledged = true;
    }
}
