<?php

namespace Thunk\Verbs;

use Godruoyi\Snowflake\Snowflake;
use Spatie\LaravelPackageTools\Package;
use Thunk\Verbs\Commands\SkeletonCommand;
use Godruoyi\Snowflake\LaravelSequenceResolver;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class VerbsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('verbs')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_events_table')
            ->hasCommand(SkeletonCommand::class);
    }

    public function register()
    {
        parent::register();
        
        $this->app->singleton('snowflake', function ($app) {
            return (new Snowflake())
                ->setStartTimeStamp(
                    strtotime(
                        config('verbs.snowflake_start_date', '2018-10-07')
                    ) * 1000
                )->setSequenceResolver(new LaravelSequenceResolver($app->get('cache.store')));
        });
    }
}
