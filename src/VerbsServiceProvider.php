<?php

namespace Thunk\Verbs;

use Godruoyi\Snowflake\LaravelSequenceResolver;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Date;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Thunk\Verbs\Contracts\Store as StoreContract;
use Thunk\Verbs\Lifecycle\Broker;
use Thunk\Verbs\Lifecycle\Bus;
use Thunk\Verbs\Lifecycle\Store;
use Thunk\Verbs\Support\Snowflake;

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
	    $this->app->alias(Bus::class, Contracts\Bus::class);

        $this->app->singleton(Store::class);
		$this->app->alias(Store::class, Contracts\Store::class);

        $this->app->singleton(Broker::class);
	    $this->app->alias(Broker::class, Contracts\Broker::class);

        $this->app->singleton(Snowflake::class, function (Container $app) {
            $datacenter = config('verbs.snowflake_datacenter_id');
            $worker = config('verbs.snowflake_worker_id');
            $start_date = config('verbs.snowflake_start_date');

            return (new Snowflake($datacenter, $worker))
                ->setStartTimeStamp(Date::parse($start_date)->getPreciseTimestamp(3))
                ->setSequenceResolver(new LaravelSequenceResolver($app->make('cache.store')));
        });
    }
}
