<?php

use Thunk\Verbs\Event;
use Thunk\Verbs\Exceptions\MigratorException;
use Thunk\Verbs\SerializedByVerbs;
use Thunk\Verbs\ShouldMigrateData;
use Thunk\Verbs\State;
use Thunk\Verbs\Support\Migrations;
use Thunk\Verbs\Support\Normalization\NormalizeToPropertiesAndClassName;
use Thunk\Verbs\Support\Serializer;

it('migrates an Event when deserializing', function () {
    $event = app(Serializer::class)->deserialize(EventWithMigration::class, []);

    expect($event->migrated)->toBeTrue();
});

it('migrates a State when deserializing', function () {
    $state = app(Serializer::class)->deserialize(StateWithMigration::class, []);

    expect($state->migrated)->toBeTrue();
});

it('migrates a class that implements SerializedByVerbs', function () {
    $dto = app(Serializer::class)->deserialize(DTOWithMigration::class, []);

    expect($dto->migrated)->toBeTrue();
});

it('migrates with a Migrations class', function () {
    $target = new class extends State
    {
        public DTOWithMigration $dto;
    };
    $data = '{"dto":{"fqcn":"DTOWithMigration"}}';

    $state = app(Serializer::class)->deserialize($target, $data);

    expect($state->dto->migrated)->toBeTrue();
});

it('can migrate dtos in events', function () {
    $event = '{"dto":{"fqcn":"DTOWithMigration"}}';

    $event = app(Serializer::class)->deserialize(EventWithDto::class, json_decode($event, true));

    expect($event->dto->migrated)->toBeTrue();
});

it('can migrate dtos in states', function () {
    $state = '{"dto":{"fqcn":"DTOWithMigration"}}';

    $state = app(Serializer::class)->deserialize(StateWithMigration::class, json_decode($state, true));

    expect($state->migrated)->toBeTrue();
});

it('matches methods to migrations', function () {
    $migrator = new class extends Migrations
    {
        public function v() {}

        public function not_a_migration() {}

        public function v_() {}

        public function _v0() {}

        public function v0(array $data)
        {
            return $data;
        }

        public function v1_named_method(array $data)
        {
            return $data;
        }

        public function v3_skip_a_version(array $data)
        {
            return $data;
        }

        public function v04_leading_0($data)
        {
            return $data;
        }

        public function v10_two_digits($data)
        {
            return $data;
        }

        public function v15CamelCase($data)
        {
            return $data;
        }
    };

    $migrations = $migrator->getMigrations();

    expect($migrations)
        ->toHaveCount(6)
        ->toHaveKeys([0, 1, 3, 4, 10, 15]);
});

it('can migrate static methods', function () {
    $migrator = new class extends Migrations
    {
        public static function v0(array $data): array
        {
            return array_merge($data, ['migrated' => true]);
        }
    };

    $data = $migrator::migrate($migrator->getMigrations(), []);

    expect($data['migrated'])->toBeTrue();
});

it('throws an exception when a migration has a bad return type', function () {
    $migrator = new class extends Migrations
    {
        public function v0(array $data): string
        {
            return '';
        }
    };

    $migrator::migrate($migrator->getMigrations(), []);
})->throws(MigratorException::class, 'accept and return an array');

it('throws an exception when a migration has a bad parameter type', function () {
    $migrator = new class extends Migrations
    {
        public function v0(string $data): array
        {
            return [];
        }
    };

    $migrator::migrate($migrator->getMigrations(), []);
})->throws(MigratorException::class, 'accept and return an array');

it('throws an exception when a migration has a duplicate version', function () {
    // create dynamic class
    $migrator = new class extends Migrations
    {
        public function v1(array $data): array
        {
            return [];
        }

        public function v01_duplicate(array $data): array
        {
            return [];
        }
    };
    $migrator::migrate($migrator->getMigrations(), []);
})->throws(MigratorException::class, 'Duplicate migration version');

it('throws an exception when a migration is not a callable', function () {
    $migrations = [
        0 => 'not a callable',
    ];

    Migrations::migrate($migrations, []);

})->throws(MigratorException::class, 'callable');

it("doesn't rerun migrations", function () {
    $migrator = new class extends Migrations
    {
        public function v0(array $data): array
        {
            return ['migrated' => $data['old_data']];
        }
    };
    $data = [
        'old_data' => 'moved',
    ];

    $data = $migrator::migrate($migrator->getMigrations(), $data);
    $data = $migrator::migrate($migrator->getMigrations(), $data);

    expect($data['migrated'])->toBe('moved');
});

it('stores migration versions on events', function () {
    $event = new class extends Event implements ShouldMigrateData
    {
        public bool $migrated;

        public static function migrations(): Migrations|array
        {
            return [
                0 => fn () => [],
            ];
        }
    };

    $serialized = app(Serializer::class)->serialize($event);

    expect(json_decode($serialized, true)['__vn'])->toBe(0);
});

it('throws an exception when strings are used as migration versions', function () {
    $migrations = [
        'test' => fn () => [],
    ];
    $migrator = new TestMigrations();

    $migrator::migrate($migrations, []);
})->throws(MigratorException::class, 'integer');

it('store migration versions on states', function () {
    $state = new class extends State implements ShouldMigrateData
    {
        public static function migrations(): Migrations|array
        {
            return [
                0 => fn () => [],
            ];
        }
    };

    $serialized = app(Serializer::class)->serialize($state);

    expect(json_decode($serialized, true)['__vn'])->toBe(0);
});

it('stores migration versions on classes', function () {
    $target = new class implements SerializedByVerbs, ShouldMigrateData
    {
        use NormalizeToPropertiesAndClassName;

        public static function migrations(): Migrations|array
        {
            return [
                0 => fn () => [],
            ];
        }
    };

    $serialized = app(Serializer::class)->serialize($target);

    expect(json_decode($serialized, true)['__vn'])->toBe(0);
});

class DTOWithVersion implements SerializedByVerbs, ShouldMigrateData
{
    use NormalizeToPropertiesAndClassName;

    public int $__vn = 0;

    public static function migrations(): Migrations|array
    {
        return [];
    }
}

class DTO implements SerializedByVerbs
{
    use NormalizeToPropertiesAndClassName;
}

class EventWithDto extends Event
{
    public function __construct(
        public DTOWithMigration $dto
    ) {}
}

class DTOWithMigration implements SerializedByVerbs, ShouldMigrateData
{
    use NormalizeToPropertiesAndClassName;

    public bool $migrated;

    public static function migrations(): Migrations|array
    {
        return [
            0 => fn ($data) => array_merge($data, ['migrated' => true]),
        ];
    }
}

class EventWithMigration extends Event implements ShouldMigrateData
{
    public function __construct(
        public bool $migrated,
    ) {}

    public static function migrations(): array
    {
        return [
            0 => fn ($data) => array_merge($data, ['migrated' => true]),
        ];
    }
}

class StateWithMigration extends State implements ShouldMigrateData
{
    public $migrated;

    public static function migrations(): array
    {
        return [
            0 => fn ($data) => array_merge($data, ['migrated' => true]),
        ];
    }
}

class DtoWithMigrationClass implements SerializedByVerbs, ShouldMigrateData
{
    use NormalizeToPropertiesAndClassName;

    public bool $migrated;

    public static function migrations(): Migrations
    {
        return new TestMigrations();
    }
}

class TestMigrations extends Migrations
{
    public function v0(array $data): array
    {
        return array_merge($data, ['migrated' => true]);
    }
}
