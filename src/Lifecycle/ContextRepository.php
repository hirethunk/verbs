<?php

namespace Thunk\Verbs\Lifecycle;

use Illuminate\Contracts\Container\Container;
use Thunk\Verbs\Context;
use Thunk\Verbs\Contracts\ManagesContext;
use Thunk\Verbs\Contracts\StoresEvents;
use Thunk\Verbs\Event;
use Thunk\Verbs\Snowflakes\Snowflake;

class ContextRepository implements ManagesContext
{
    protected array $contexts = [];

    public function __construct(
        protected Container $container,
        protected StoresEvents $events,
    ) {
    }

    public function apply(Event $event): void
    {
        // Apply this event to all loaded snapshots, checking versions somehow,
        // and throw an exception on version mismatches.

        // We probably first need to pull new events from the event repository,
        // which most likely happens by just calling get()

        // Snapshots can have a `last_applied_event_id` that we can use to compare
        // the timestamps. This should let us avoid an `aggregate_version`
    }

    public function get(string $class_name, Snowflake $id): Context
    {
        // First, load internal 'singleton' instance of snapshot
        $context = $this->contexts[$class_name][(string) $id] ?? $this->container->make($class_name);

        // Then, query database for events that happened since the most recent
        // event that was applied to the snapshot, and apply those events
        // in order
    }
}
