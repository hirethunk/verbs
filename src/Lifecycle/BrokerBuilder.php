<?php

namespace Thunk\Verbs\Lifecycle;

use Illuminate\Support\DateFactory;
use Thunk\Verbs\Contracts\BrokersEvents;
use Thunk\Verbs\Contracts\StoresEvents;
use Thunk\Verbs\Contracts\StoresSnapshots;
use Thunk\Verbs\Lifecycle\Queue as EventQueue;
use Thunk\Verbs\Support\EventStateRegistry;
use Thunk\Verbs\Support\Wormhole;
use Thunk\Verbs\Testing\BrokerFake;
use Thunk\Verbs\Testing\EventStoreFake;
use Thunk\Verbs\Testing\SnapshotStoreFake;

class BrokerBuilder
{
    public string $broker_type = Broker::class;

    public string $event_store = EventStore::class;

    public string $event_queue = EventQueue::class;

    public string $snapshot_store = SnapshotStore::class;

    public string $state_manager = StateManager::class;

    public string $metadata = MetadataManager::class;

    public string $event_state_registry = EventStateRegistry::class;

    public string $auto_commit_manager = AutoCommitManager::class;

    public string $wormhole = Wormhole::class;

    public static function primary(): BrokersEvents
    {
        return (new static)->build();
    }

    public static function fake(): BrokersEvents
    {
        return (new static)
            ->ofType(BrokerFake::class)
            ->withEventStore(EventStoreFake::class)
            ->withSnapshotStore(SnapshotStoreFake::class)
            ->build();
    }

    public function ofType(string $broker_type): static
    {
        $this->broker_type = ensure_type($broker_type, BrokersEvents::class);

        return $this;
    }

    public function withEventStore(string $event_store): static
    {
        $this->event_store = ensure_type($event_store, StoresEvents::class);

        return $this;
    }

    public function withEventQueue(string $event_queue): static
    {
        $this->event_queue = ensure_type($event_queue, EventQueue::class);

        return $this;
    }

    public function withSnapshotStore(string $snapshot_store): static
    {
        $this->snapshot_store = ensure_type($snapshot_store, StoresSnapshots::class);

        return $this;
    }

    public function withStateManager(string $state_manager): static
    {
        $this->state_manager = ensure_type($state_manager, StateManager::class);

        return $this;
    }

    public function withMetadata(string $metadata): static
    {
        $this->metadata = ensure_type($metadata, MetadataManager::class);

        return $this;
    }

    public function withEventStateRegistry(string $event_state_registry): static
    {
        $this->event_state_registry = ensure_type($event_state_registry, EventStateRegistry::class);

        return $this;
    }

    public function withAutoCommitManager(string $auto_commit_manager): static
    {
        $this->auto_commit_manager = ensure_type($auto_commit_manager, AutoCommitManager::class);

        return $this;
    }

    public function withWormhole(string $wormhole): static
    {
        $this->wormhole = ensure_type($wormhole, Wormhole::class);

        return $this;
    }

    public function build(): BrokersEvents
    {
        // @todo - is this bad?
        $dispatcher = app(Dispatcher::class);

        $metadata = new MetadataManager();

        $wormhole = new $this->wormhole(
            metadata: $metadata,
            factory: app(DateFactory::class),
            enabled: config('verbs.wormhole', true),
        );

        $event_store = new $this->event_store(
            metadata: $metadata
        );

        $event_queue = new $this->event_queue(
            event_store: $event_store
        );

        $snapshot_store = new $this->snapshot_store;

        $state_manager = new $this->state_manager(
            dispatcher: $dispatcher,
            snapshots: $snapshot_store,
            events: $event_store,
        );

        $event_state_registry = new $this->event_state_registry($state_manager);

        $broker = new $this->broker_type(
            dispatcher: $dispatcher,
            metadata: $metadata,
            wormhole: $wormhole,
            event_state_registry: $event_state_registry,
            event_store: $event_store,
            event_queue: $event_queue,
            snapshot_store: $snapshot_store,
            state_manager: $state_manager,
        );

        $auto_commit_manager = new $this->auto_commit_manager(
            broker: $broker,
            enabled: config('verbs.autocommit', true),
        );

        $broker->auto_commit_manager = $auto_commit_manager;

        return $broker;
    }
}
