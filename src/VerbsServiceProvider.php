<?php

namespace Thunk\Verbs;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Thunk\Verbs\Lifecycle\Broker;
use Thunk\Verbs\Lifecycle\Dispatcher;
use Thunk\Verbs\Lifecycle\EventStore;
use Thunk\Verbs\Lifecycle\Queue as EventQueue;
use Thunk\Verbs\Lifecycle\StateStore;

class VerbsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('verbs')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigrations(
                'create_verb_events_table',
                'create_verb_state_events_table',
            );
    }

    public function packageRegistered()
    {
        $this->app->singleton(Broker::class);
        $this->app->singleton(Dispatcher::class);
        $this->app->singleton(EventStore::class);
        $this->app->singleton(EventQueue::class);
        $this->app->singleton(StateStore::class);
    }

    public function boot()
    {
        $this->app->terminating(function () {
            app(Broker::class)->commit();
        });
    }
}
