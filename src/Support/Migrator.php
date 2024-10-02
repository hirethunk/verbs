<?php

namespace Thunk\Verbs\Support;

use Thunk\Verbs\Exceptions\MigratorException;
use Thunk\Verbs\ShouldMigrateData;

class Migrator
{
    public static function migrate(ShouldMigrateData|array $migrations, array $data): array
    {
        if ($migrations instanceof ShouldMigrateData) {
            $migrations = $migrations->migrations();
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
                    throw new \TypeError;
                }
            } catch (\TypeError $e) {
                throw new MigratorException("Migration Failed: v$key must accept and return an array");
            }
            $version = $key;
        }

        if ($version === -1) {
            return $data;
        }

        return array_merge($data, ['__vn' => $version]);
    }
}
