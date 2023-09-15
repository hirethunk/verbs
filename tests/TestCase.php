<?php

namespace Thunk\Verbs\Tests;

use Glhd\Bits\Support\BitsServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use InterNACHI\Modular\Support\ModularServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use SqlFormatter;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Thunk\Verbs\VerbsServiceProvider;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            function (string $modelName) {
                return str($modelName)
                    ->replace('\\Models\\', '\\Database\\Factories\\')
                    ->append('Factory')
                    ->toString();
            }
        );
    }

    protected function getPackageProviders($app)
    {
        return [
            ModularServiceProvider::class, // This must register first
            VerbsServiceProvider::class,
            BitsServiceProvider::class,
        ];
    }

    protected function defineDatabaseMigrations()
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $events = include __DIR__.'/../database/migrations/create_verb_events_table.php.stub';
        $events->up();

        $snapshots = include __DIR__.'/../database/migrations/create_verb_snapshots_table.php.stub';
        $snapshots->up();
    }

    protected function watchDatabaseQueries(): self
    {
        $formatter = new OutputFormatter();
        $output = new ConsoleOutput(ConsoleOutput::VERBOSITY_NORMAL, null, $formatter);
        $style = new SymfonyStyle(new ArgvInput(), $output);

        DB::listen(function(QueryExecuted $event) use ($style) {
            $when = now()->toTimeString();
            $style->section("[{$when}] Executed in {$event->time}ms on via '{$event->connectionName}' connection:");
            $style->writeln($event->sql);
            $style->writeln(json_encode($event->bindings));
        });

        return $this;
    }
}
