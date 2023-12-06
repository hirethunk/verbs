<?php

namespace Thunk\Verbs\Tests;

use Glhd\Bits\Support\BitsServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InterNACHI\Modular\Support\FinderCollection;
use Orchestra\Testbench\TestCase as Orchestra;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\SplFileInfo;
use Thunk\Verbs\VerbsServiceProvider;

use function Orchestra\Testbench\artisan;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            BitsServiceProvider::class,
            VerbsServiceProvider::class,
        ];
    }

    protected function defineDatabaseMigrations()
    {
        $options = [
            '--path' => [
                __DIR__.'/../database/migrations',
                "{$this->getExamplePath()}/database/migrations"
            ],
            '--realpath' => true,
        ];

        artisan($this, 'migrate:fresh', $options);
    }

    /** @param  Application  $app */
    public function getEnvironmentSetUp($app)
    {
        Factory::guessFactoryNamesUsing(
            function (string $modelName) {
                return str($modelName)
                    ->replace('\\Models\\', '\\Database\\Factories\\')
                    ->append('Factory')
                    ->toString();
            }
        );

        FinderCollection::forFiles()
            ->depth(0)
            ->name('*.php')
            ->sortByName()
            ->inOrEmpty("{$this->getExamplePath()}/routes/")
            ->each(fn (SplFileInfo $file) => require $file->getRealPath());

        // TODO: Factories
        // TODO: Views
        // TODO: Blade Components
        // TODO: Commands
    }

    protected function getExamplePath(): string
    {
        $example = Str::of(static::class)->after('Examples\\')->before('\\');
        $example_path = realpath(__DIR__.'/../examples/'.$example);

        return $example_path;
    }

    protected function watchDatabaseQueries(): self
    {
        $formatter = new OutputFormatter();
        $output = new ConsoleOutput(ConsoleOutput::VERBOSITY_NORMAL, null, $formatter);
        $style = new SymfonyStyle(new ArgvInput(), $output);

        DB::listen(function (QueryExecuted $event) use ($style) {
            $when = now()->toTimeString();
            $style->section("[{$when}] Executed in {$event->time}ms on via '{$event->connectionName}' connection:");
            $style->writeln($event->sql);
            $style->writeln(json_encode($event->bindings));
        });

        return $this;
    }
}
