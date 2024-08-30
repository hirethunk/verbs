<?php

namespace Thunk\Verbs\Support;

use Thunk\Verbs\Attributes\Migrations\MigrationUsing;
use Thunk\Verbs\Attributes\Migrations\PropertyAdded;
use Thunk\Verbs\Attributes\Migrations\PropertyAddedUsing;
use Thunk\Verbs\Attributes\Migrations\PropertyMigrated;
use Thunk\Verbs\Attributes\Migrations\PropertyMigratedUsing;
use Thunk\Verbs\Attributes\Migrations\PropertyRemoved;
use Thunk\Verbs\Exceptions\MigratorException;

trait HasMigrations
{
    public function migrations(): Migrations|array
    {
        $migrations = [];

        $instance = new \ReflectionClass($this);

        $newMigration = $this->migrationsForClass($instance->getAttributes());
        $migrations = $this->safeMergeMigrations($migrations, $newMigration);

        foreach ($instance->getMethods() as $method) {
            $newMigration = $this->migrationsForMethod($method->getAttributes(), $method);
            $migrations = $this->safeMergeMigrations($migrations, $newMigration);
        }

        foreach ($instance->getProperties() as $property) {
            $newMigration = $this->migrationsForProperty($property->getAttributes(), $property);
            $migrations = $this->safeMergeMigrations($migrations, $newMigration);
        }

        ksort($migrations);

        $generatedMigrations = [];

        foreach ($migrations as $version => $migrate) {
            $generatedMigrations[$version] = function (array $data) use ($migrate) {
                return $migrate($data);
            };
        }

        return $generatedMigrations;
    }

    private function migrationsForMethod(array $attributes, \ReflectionMethod $method): array
    {
        $migrations = [];

        foreach ($attributes as $attribute) {
            $attrInstance = $attribute->newInstance();

            if ($attrInstance instanceof PropertyMigratedUsing) {
                $migrations = $this->setMigrationForVersion($attrInstance->version, $migrations, function (array $data) use ($method, $attrInstance) {
                    return $attrInstance->migrate(static::class, $method->getName(), $data);
                });
            }

            if ($attrInstance instanceof PropertyAddedUsing) {
                $migrations = $this->setMigrationForVersion($attrInstance->version, $migrations, function (array $data) use ($method, $attrInstance) {
                    return $attrInstance->migrate(static::class, $method->getName(), $data);
                });
            }

            if ($attrInstance instanceof PropertyRemoved) {
                $migrations = $this->setMigrationForVersion($attrInstance->version, $migrations, function (array $data) use ($attrInstance) {
                    return $attrInstance->migrate($data);
                });
            }

            if ($attrInstance instanceof MigrationUsing) {
                $migrations = $this->setMigrationForVersion($attrInstance->version, $migrations, function (array $data) use ($method, $attrInstance) {
                    return $attrInstance->migrate(static::class, $method->getName(), $data);
                });
            }
        }

        return $migrations;
    }

    private function migrationsForProperty(array $attributes, \ReflectionProperty $property): array
    {
        $migrations = [];

        foreach ($attributes as $attribute) {
            $attrInstance = $attribute->newInstance();

            if ($attrInstance instanceof PropertyAdded) {
                $migrations = $this->setmigrationForVersion($attrInstance->version, $migrations, function (array $data) use ($property, $attrInstance) {
                    return $attrInstance->migrate($property->getName(), $data);
                });
            }

            if ($attrInstance instanceof PropertyMigrated) {
                $migrations = $this->setMigrationForVersion($attrInstance->version, $migrations, function (array $data) use ($property, $attrInstance) {
                    return $attrInstance->migrate(static::class, $property->getName(), $data);
                });
            }
        }

        return $migrations;
    }

    private function migrationsForClass(array $attributes): array
    {
        $migrations = [];

        foreach ($attributes as $attribute) {
            $attrInstance = $attribute->newInstance();

            if ($attrInstance instanceof PropertyRemoved) {
                $migrations = $this->setMigrationForVersion($attrInstance->version, $migrations, function (array $data) use ($attrInstance) {
                    return $attrInstance->migrate($data);
                });
            }
        }

        return $migrations;
    }

    private function setMigrationForVersion(int $version, array $migrations, callable $migration): array
    {
        if (isset($migrations[$version])) {
            throw new MigratorException("Duplicate migration version: {$version} matches another migration with the same version number.");
        }

        $migrations[$version] = $migration;

        return $migrations;
    }

    private function safeMergeMigrations(mixed $migrations, array $newMigration)
    {
        foreach ($newMigration as $version => $migration) {
            $migrations = $this->setMigrationForVersion($version, $migrations, $migration);
        }

        return $migrations;
    }
}
