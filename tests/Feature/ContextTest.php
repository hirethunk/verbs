<?php

use Thunk\Verbs\Events\AttachedToParent;
use Thunk\Verbs\Events\ChildAttached;
use Thunk\Verbs\Facades\Broker;
use Thunk\Verbs\Facades\Bus;
use Thunk\Verbs\Facades\Contexts;
use Thunk\Verbs\Facades\Snowflake;
use Thunk\Verbs\Facades\Store;
use Thunk\Verbs\Tests\Fixtures\Contexts\ChildContext;
use Thunk\Verbs\Tests\Fixtures\Contexts\GenericContext;
use Thunk\Verbs\Tests\Fixtures\Contexts\ParentContext;
use Thunk\Verbs\Tests\Fixtures\Events\EventCreatedContext;
use Thunk\Verbs\Tests\Fixtures\Events\EventWasFired;

beforeEach(function () {
    Store::fake();
});

it('Context is applied when events are fired', function () {
    Contexts::fake();

    EventWasFired::fire('foo');

    Contexts::assertApplied(EventWasFired::class);
});

it('creates context', function() {
    Bus::fake();
    
    EventCreatedContext::fire('bar');
    
    Bus::assertDispatched(function (EventCreatedContext $event) {
        return $event->context instanceof GenericContext;
    });
});

it('can have context attached', function () {
    Bus::fake();

    EventWasFired::withContext(new GenericContext(Snowflake::make()))->fire('bar');

    Bus::assertDispatched(function (EventWasFired $event) {
        return $event->context instanceof GenericContext;
    });
});

it('can attach parent/child context', function () {
    Bus::fake();

    $parent = new ParentContext(Snowflake::make());
    $child = new ChildContext(Snowflake::make());
    
    $parent->attachChild($child);

    Bus::assertDispatched(function (AttachedToParent $event) use ($parent, $child) {
        return $event->context->id === $child->id 
            && $event->parent_id === $parent->id;
    });

    Bus::assertDispatched(function (ChildAttached $event) use ($parent, $child) {
        return $event->context->id === $parent->id 
            && $event->child_id === $child->id;
    });
    
    Broker::replay();

    Bus::assertReplayed(function (AttachedToParent $event) use ($parent, $child) {
        return $event->context->id === $child->id
            && $event->parent_id === $parent->id;
    });

    Bus::assertReplayed(function (ChildAttached $event) use ($parent, $child) {
        return $event->context->id === $parent->id
            && $event->child_id === $child->id;
    });
});
