<?php

use Thunk\Verbs\Event;
use Thunk\Verbs\State;
use Thunk\Verbs\Support\StateCollection;

it('supports using states directly in events', function () {
    $user_request = UserRequestState::new();

    UserRequestAcknowledged::commit(
        user_request: $user_request
    );

    $this->assertTrue($user_request->acknowledged);
});

it('accepts an id and loads the state', function () {
    $user_request = UserRequestState::new();

    UserRequestAcknowledged::commit(
        user_request: $user_request->id
    );

    $this->assertTrue($user_request->acknowledged);
});

it('supports singleton states', function () {
    $user_request = UserRequestState::singleton();

    UserRequestAcknowledged::commit(
        user_request: $user_request
    );

    $this->assertTrue($user_request->acknowledged);
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
    $user_request1 = UserRequestState::new();
    $user_request2 = UserRequestState::new();

    $user_requests = new StateCollection([
        $user_request1,
        $user_request2,
    ]);

    UserRequestsProcessed::commit(
        user_requests: $user_requests
    );

    $this->assertTrue($user_request1->processed);
    $this->assertTrue($user_request2->processed);
});

it('loads the correct state when multiple are used', function () {
    $user_request1 = UserRequestState::new();

    $event1 = UserRequestAcknowledged::fire(
        user_request: $user_request1
    );

    $this->assertTrue($user_request1->acknowledged);

    $this->assertEquals($event1->id, $user_request1->last_event_id);

    $user_request2 = UserRequestState::new();

    $event2 = UserRequestAcknowledged::fire(
        user_request: $user_request2
    );

    $this->assertTrue($user_request2->acknowledged);

    $this->assertEquals($event2->id, $user_request2->last_event_id);
});

class UserRequestState extends State
{
    public bool $acknowledged = false;

    public bool $processed = false;
}

class UserRequestAcknowledged extends Event
{
    public function __construct(
        public UserRequestState $user_request
    ) {}

    public function apply()
    {
        $this->user_request->acknowledged = true;
    }
}

class UserRequestsProcessed extends Event
{
    public function __construct(
        public StateCollection $user_requests
    ) {}

    public function apply()
    {
        $this->user_requests->each(
            fn (UserRequestState $user_request) => $user_request->processed = true
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
