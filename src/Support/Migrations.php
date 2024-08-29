<?php

namespace Thunk\Verbs\Support;

use ReflectionClass;
use Thunk\Verbs\Contracts\MigratesData;
use Thunk\Verbs\Exceptions\MigratorException;

abstract class Migrations implements MigratesData
{
    /**
     * @param  Migrations|array<int, \Closure>  $migrations
     *
     * @throws MigratorException
     */
    public static function migrate(Migrations|array $migrations, array $data): array
    {
        if ($migrations instanceof MigratesData) {
            $migrations = $migrations->getMigrations();
        }

        // All keys must be integers
        if (count(array_filter(array_keys($migrations), 'is_int')) !== count($migrations)) {
            throw new MigratorException('Migrations must have explicit integer keys.');
        }

        ksort($migrations);

        $version = $data['__vn'] ?? -1;

        foreach ($migrations as $key => $migration) {
            if ($key <= $version) {
                continue;
            }

            if (! is_callable($migration)) {
                throw new MigratorException('Invalid migration provided. Must be a callable.');
            }
            try {
                $data = $migration($data);
                if (! is_array($data)) {
                    throw new \TypeError();
                }
            } catch (\TypeError $e) {
                throw new MigratorException("Migration Failed: v$key must accept and return an array");
            }
            $version = $key;
        }

        return array_merge($data, ['__vn' => $version]);
    }

    /**
     * @throws MigratorException
     */
    public function getMigrations(): array
    {
        $migrations = [];

        $methods = (new ReflectionClass($this))->getMethods();
        foreach ($methods as $method) {
            if (preg_match('/^v(\d+)/', $method->name, $matches)) {
                $version = (int) $matches[1];
                if (isset($migrations[$version])) {
                    throw new MigratorException("Duplicate migration version: {$method->name} matches another migration with the same version number.");
                }
                $migrations[$version] = $method->getClosure($this);
            }
        }

        return $migrations;
    }
}
