<?php

namespace Thunk\Verbs;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Date;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer as SymfonySerializer;
use Thunk\Verbs\Lifecycle\Broker;
use Thunk\Verbs\Lifecycle\Bus;
use Thunk\Verbs\Lifecycle\ContextRepository;
use Thunk\Verbs\Lifecycle\EventRepository;
use Thunk\Verbs\Lifecycle\Serializer;
use Thunk\Verbs\Snowflakes\Bits;
use Thunk\Verbs\Snowflakes\CacheSequenceResolver;
use Thunk\Verbs\Snowflakes\Factory;

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
        $this->app->singleton(Bus::class);
        $this->app->alias(Bus::class, Contracts\DispatchesEvents::class);

        $this->app->singleton(EventRepository::class);
        $this->app->alias(EventRepository::class, Contracts\StoresEvents::class);

        $this->app->singleton(ContextRepository::class);
        $this->app->alias(ContextRepository::class, Contracts\ManagesContext::class);

        $this->app->singleton(Broker::class);
        $this->app->alias(Broker::class, Contracts\BrokersEvents::class);

        $this->app->singleton(Bits::class);

        $this->app->singleton(CacheSequenceResolver::class);
        $this->app->alias(CacheSequenceResolver::class, Contracts\ResolvesSequences::class);
        
        $this->app->singleton(Serializer::class, function () {
            $encoders = [new JsonEncoder()];
            $normalizers = [new ObjectNormalizer()];
            
            return new Serializer(new SymfonySerializer($normalizers, $encoders));
        });
        $this->app->alias(Serializer::class, Contracts\SerializesAndRestoresEvents::class);

        $this->app->singleton(Factory::class, function (Container $container) {
            return new Factory(
                epoch: Date::parse(config('verbs.snowflake_start_date')),
                datacenter_id: (int) (config('verbs.snowflake_datacenter_id') ?? random_int(0, 31)),
                worker_id: (int) (config('verbs.snowflake_worker_id') ?? random_int(0, 31)),
                precision: 3,
                sequence: $container->make(Contracts\ResolvesSequences::class),
                bits: $container->make(Bits::class),
            );
        });
    }
}
