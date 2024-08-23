<?php

use Thunk\Verbs\Event;
use Thunk\Verbs\State;
use Thunk\Verbs\Support\StateCollection;

it('supports using states directly in events', function () {
    $contact_request = ContactRequestState::new();

    ContactRequestAcknowledged::commit(
        contact_request: $contact_request
    );

    $this->assertTrue($contact_request->acknowledged);
});

it('accepts an id and loads the state', function () {
    $contact_request = ContactRequestState::new();

    ContactRequestAcknowledged::commit(
        contact_request: $contact_request->id
    );

    $this->assertTrue($contact_request->acknowledged);
});

it('supports singleton states', function () {
    $contact_request = ContactRequestState::singleton();

    ContactRequestAcknowledged::commit(
        contact_request: $contact_request
    );

    $this->assertTrue($contact_request->acknowledged);
});

it('supports using a nested state directly in events', function () {
    $parent = ParentState::new();
    $child = ChildState::new();
    ChildAddedToParent::commit(
        parent: $parent,
        child: $child,
    );

    $this->assertEquals($child, $parent->child);

    $this->assertEquals(0, $child->count);

    NestedStateAccessed::commit(parent: $parent);

    $this->assertEquals(1, $child->count);
});

it('supports state collections', function () {
    $contact_request1 = ContactRequestState::new();
    $contact_request2 = ContactRequestState::new();

    $contact_requests = new StateCollection([
        $contact_request1,
        $contact_request2,
    ]);

    ContactRequestsProcessed::commit(
        contact_requests: $contact_requests
    );

    $this->assertTrue($contact_request1->processed);
    $this->assertTrue($contact_request2->processed);
});

class ContactRequestState extends State
{
    public bool $acknowledged = false;

    public bool $processed = false;
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

class ContactRequestsProcessed extends Event
{
    public function __construct(
        public StateCollection $contact_requests
    ) {}

    public function apply()
    {
        $this->contact_requests->each(
            fn (ContactRequestState $contact_request) => $contact_request->processed = true
        );
    }
}

class ParentState extends State
{
    public ChildState $child;
}

class ChildState extends State
{
    public int $count = 0;
}

class ChildAddedToParent extends Event
{
    public ParentState $parent;

    public ChildState $child;

    public function applyToParentState()
    {
        $this->parent->child = $this->child;
    }
}

class NestedStateAccessed extends Event
{
    public ParentState $parent;

    public function apply()
    {
        $this->parent->child->count++; // 1
    }
}
