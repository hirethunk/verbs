<?php

namespace Thunk\Verbs;

use Glhd\Bits\Snowflake;
use Illuminate\Events\Dispatcher as LaravelDispatcher;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Thunk\Verbs\Commands\MakeVerbEventCommand;
use Thunk\Verbs\Commands\MakeVerbStateCommand;
use Thunk\Verbs\Lifecycle\Broker;
use Thunk\Verbs\Lifecycle\Dispatcher;
use Thunk\Verbs\Lifecycle\EventStore;
use Thunk\Verbs\Lifecycle\MetadataManager;
use Thunk\Verbs\Lifecycle\Queue as EventQueue;
use Thunk\Verbs\Lifecycle\SnapshotStore;
use Thunk\Verbs\Lifecycle\StateManager;
use Thunk\Verbs\Livewire\SupportVerbs;
use Thunk\Verbs\Support\EventSerializer;
use Thunk\Verbs\Support\EventStateRegistry;
use Thunk\Verbs\Support\MetadataSerializer;
use Thunk\Verbs\Support\Serializer;
use Thunk\Verbs\Support\StateSerializer;

class VerbsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('verbs')
            ->hasConfigFile()
            ->hasCommands(
                MakeVerbEventCommand::class,
                MakeVerbStateCommand::class,
            )
            ->hasMigrations(
                'create_verb_events_table',
                'create_verb_snapshots_table',
                'create_verb_state_events_table',
            );
    }

    public function registeringPackage()
    {
        require_once __DIR__.'/Support/helpers.php';
    }

    public function packageRegistered()
    {
        $this->app->singleton(Broker::class);
        $this->app->singleton(Dispatcher::class);
        $this->app->singleton(EventStore::class);
        $this->app->singleton(SnapshotStore::class);
        $this->app->singleton(EventQueue::class);
        $this->app->singleton(StateManager::class);
        $this->app->singleton(EventStateRegistry::class);
        $this->app->singleton(MetadataManager::class);

        $this->app->singleton(EventSerializer::class, function () {
            return new EventSerializer(Serializer::defaultSymfonySerializer());
        });

        $this->app->singleton(MetadataSerializer::class, function () {
            return new MetadataSerializer(Serializer::defaultSymfonySerializer());
        });

        $this->app->singleton(StateSerializer::class, function () {
            return new StateSerializer(StateSerializer::defaultSymfonySerializer());
        });
    }

    public function boot()
    {
        parent::boot();

        if ($this->app->has('livewire')) {
            $this->app->make('livewire')->componentHook(SupportVerbs::class);
        }

        $this->app->terminating(function () {
            app(Broker::class)->commit();
        });

        // Allow for firing events with traditional Laravel dispatcher
        $this->app->make(LaravelDispatcher::class)->listen('*', function (string $name, array $data) {
            [$event] = $data;
            if (isset($event) && $event instanceof Event) {
                $event->id ??= Snowflake::make()->id();
                $this->app->make(Broker::class)->fire($event);
            }
        });
    }
}
