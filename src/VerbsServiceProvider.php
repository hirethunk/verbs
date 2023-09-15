<?php

namespace Thunk\Verbs;

use InterNACHI\Modular\Support\ModuleRegistry;
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
            ->hasMigration('create_verb_events_table');
    }

    public function packageRegistered()
    {
        if ($this->app->runningUnitTests()) {
            // This tricks `internachi/modular` into treating the `examples` directory
            // as though it were an app module. Using modular allows us to easily
            // autoload and auto-discover example code as though it were a Laravel app.
            $this->app->singleton(ModuleRegistry::class, function () {
                return new ModuleRegistry(
                    realpath(__DIR__.'/../examples'),
                    $this->app->bootstrapPath('cache/modules.php')
                );
            });
        }

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
