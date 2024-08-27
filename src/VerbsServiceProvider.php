<?php

namespace Thunk\Verbs;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Events\Dispatcher as LaravelDispatcher;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Support\DateFactory;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorFromClassMetadata;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AnnotationLoader;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\Serializer\Normalizer\PropertyNormalizer;
use Symfony\Component\Serializer\Serializer as SymfonySerializer;
use Thunk\Verbs\Commands\MakeVerbEventCommand;
use Thunk\Verbs\Commands\MakeVerbStateCommand;
use Thunk\Verbs\Commands\ReplayCommand;
use Thunk\Verbs\Contracts\BrokersEvents;
use Thunk\Verbs\Contracts\StoresEvents;
use Thunk\Verbs\Contracts\StoresSnapshots;
use Thunk\Verbs\Lifecycle\AutoCommitManager;
use Thunk\Verbs\Lifecycle\Broker;
use Thunk\Verbs\Lifecycle\Dispatcher;
use Thunk\Verbs\Lifecycle\EventStore;
use Thunk\Verbs\Lifecycle\MetadataManager;
use Thunk\Verbs\Lifecycle\Queue as EventQueue;
use Thunk\Verbs\Lifecycle\SnapshotStore;
use Thunk\Verbs\Lifecycle\StateManager;
use Thunk\Verbs\Livewire\SupportVerbs;
use Thunk\Verbs\Support\EventStateRegistry;
use Thunk\Verbs\Support\IdManager;
use Thunk\Verbs\Support\Serializer;
use Thunk\Verbs\Support\StateInstanceCache;
use Thunk\Verbs\Support\Wormhole;

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
                ReplayCommand::class,
            );
    }

    public function registeringPackage()
    {
        require_once __DIR__.'/Support/helpers.php';
    }

    public function packageRegistered()
    {
        $this->app->scoped(Broker::class);
        $this->app->scoped(Dispatcher::class);
        $this->app->scoped(EventStore::class);
        $this->app->singleton(SnapshotStore::class);
        $this->app->scoped(EventQueue::class);
        $this->app->scoped(EventStateRegistry::class);
        $this->app->singleton(MetadataManager::class);

        $this->app->scoped(StateManager::class, function (Container $app) {
            return new StateManager(
                dispatcher: $app->make(Dispatcher::class),
                snapshots: $app->make(StoresSnapshots::class),
                events: $app->make(StoresEvents::class),
                states: new StateInstanceCache(
                    capacity: $app->make(Repository::class)->get('verbs.state_cache_size', 100)
                ),
            );
        });

        $this->app->singleton(IdManager::class, function (Container $app) {
            return new IdManager(
                id_type: $app->make(Repository::class)->get('verbs.id_type', 'snowflake'),
            );
        });

        $this->app->singleton(Wormhole::class, function (Container $app) {
            $config = $app->make(Repository::class);

            return new Wormhole(
                $app->make(MetadataManager::class),
                $app->make(DateFactory::class),
                $config->get('verbs.wormhole', true),
            );
        });

        $this->app->singleton(Serializer::class, function (Container $app) {
            return new Serializer(
                serializer: $app->make(SymfonySerializer::class),
                context: $app->make(Repository::class)->get('verbs.serializer_context', []),
            );
        });

        $this->app->singleton(PropertyNormalizer::class, function () {
            $loader = class_exists(AttributeLoader::class)
                ? new AttributeLoader
                : new AnnotationLoader;

            return new PropertyNormalizer(
                propertyTypeExtractor: new PropertyInfoExtractor(
                    typeExtractors: [
                        new PhpDocExtractor,
                        new ReflectionExtractor,
                    ]),
                classDiscriminatorResolver: new ClassDiscriminatorFromClassMetadata(new ClassMetadataFactory($loader)),
            );
        });

        $this->app->singleton(SymfonySerializer::class, function (Container $app) {
            $config = $app->make(Repository::class);

            return new SymfonySerializer(
                normalizers: collect($config->get('verbs.normalizers'))
                    ->map(fn ($class_name) => app($class_name))
                    ->values()
                    ->all(),
                encoders: [new JsonEncoder],
            );
        });

        $this->app->scoped(AutoCommitManager::class, function (Container $app) {
            return new AutoCommitManager(
                broker: $app->make(BrokersEvents::class),
                enabled: $app->make(Repository::class)->get('verbs.autocommit', true),
            );
        });

        $this->app->alias(Broker::class, BrokersEvents::class);
        $this->app->alias(EventStore::class, StoresEvents::class);
        $this->app->alias(SnapshotStore::class, StoresSnapshots::class);
    }

    public function packageBooted()
    {
        $this->publishes([
            __DIR__.'/../database/migrations/' => database_path('migrations'),
        ], "{$this->package->shortName()}-migrations");

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->has('livewire')) {
            $manager = $this->app->make('livewire');

            // Component hooks only exist in v3, so we need to check before registering our hook
            if (method_exists($manager, 'componentHook')) {
                $manager->componentHook(SupportVerbs::class);
            }
        }

        $this->app->terminating(function () {
            app(AutoCommitManager::class)->commitIfAutoCommitting();
        });

        // Hook into Laravel event dispatcher
        $this->app->make(LaravelDispatcher::class)
            ->listen('*', fn (string $name, array $data) => $this->handleEvent($data[0] ?? null));
    }

    protected function handleEvent($event = null)
    {
        // Allow for firing events with traditional Laravel dispatcher
        if ($event instanceof Event) {
            $event->id ??= snowflake_id();
            $this->app->make(BrokersEvents::class)->fire($event);
        }

        // Auto-commit after each job on the queue is processed
        if ($event instanceof JobProcessed) {
            app(AutoCommitManager::class)->commitIfAutoCommitting();
        }
    }
}
