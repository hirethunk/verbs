<?php

use Thunk\Verbs\Event;
use Thunk\Verbs\Exceptions\MigratorException;
use Thunk\Verbs\SerializedByVerbs;
use Thunk\Verbs\ShouldMigrateData;
use Thunk\Verbs\State;
use Thunk\Verbs\Support\Migrations;
use Thunk\Verbs\Support\Migrator;
use Thunk\Verbs\Support\Normalization\NormalizeToPropertiesAndClassName;
use Thunk\Verbs\Support\Serializer;

beforeEach(function () {
    $this->serializer = app(Serializer::class);
});

it('migrates when deserializing', function (string|object $class, $data) {
    $object = $this->serializer->deserialize($class, is_string($data) ? json_decode($data, true) : $data);
    expect($object->migrated ?? $object->dto->migrated ?? false)->toBeTrue();
})->with([
    'Event migration' => [EventWithMigration::class, []],
    'State migration' => [StateWithMigration::class, []],
    'DTO migration' => [DTOWithMigration::class, []],
    'DTO in events' => [EventWithMigrationDto::class, '{"dto":{"fqcn":"DTOWithMigration"}}'],
    'DTO in states' => [StateWithMigration::class, '{"dto":{"fqcn":"DTOWithMigration"}}'],
]);

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

    $migrations = $migrator->migrations();

    expect($migrations)
        ->toHaveCount(6)
        ->toHaveKeys([0, 1, 3, 4, 10, 15]);
});

it('throws exceptions for invalid migrations', function ($migrations, string $message) {
    expect(fn () => Migrator::migrate($migrations, []))->toThrow(MigratorException::class, $message);
})->with([
    'bad return type' => [[
        0 => fn (array $data): string => '',
    ], 'accept and return an array'],
    'bad parameter type' => [[
        0 => fn (string $data): array => [],
    ], 'accept and return an array'],
    'duplicate version' => [new class extends Migrations
    {
        public function v0() {}

        public function v0_duplicate() {}
    }, 'Duplicate migration version'],
    'not callable' => [[
        0 => 'not a callable',
    ], 'callable'],
    'string version' => [[
        'test' => fn () => [],
    ], 'integer'],
]);

it('increments the migration version correctly', function () {
    $migrations = [
        0 => fn (array $data) => array_merge($data, ['step1' => true]),
        1 => fn (array $data) => array_merge($data, ['step2' => true]),
    ];

    $data = [];
    $result = Migrator::migrate($migrations, $data);
    expect($result['__vn'])->toBe(1);
});

it("doesn't rerun migrations", function () {
    $migrations = [
        0 => fn (array $data) => ['migrated' => $data['old_data']],
    ];

    $data = ['old_data' => 'moved'];
    $data = Migrator::migrate($migrations, $data);
    $data = Migrator::migrate($migrations, $data);
    expect($data['migrated'])->toBe('moved');
});

it('handles no migrations gracefully', function () {
    $migrator = new class extends Migrations {};
    $data = ['key' => 'value'];
    $result = Migrator::migrate($migrator, $data);
    expect($result)->toBe($data);
});

it('executes multiple migrations in sequence', function () {
    $migrations = [
        0 => fn (array $data) => array_merge($data, ['step1' => true]),
        1 => fn (array $data) => array_merge($data, ['step2' => true]),
    ];

    $data = [];
    $data = Migrator::migrate($migrations, $data);
    expect($data)
        ->toBe(['step1' => true, 'step2' => true, '__vn' => 1]);
});

it('skips already applied migrations', function () {
    $firstMigrations = [
        0 => fn (array $data) => array_merge($data, ['step1' => true]),
    ];

    $data = Migrator::migrate($firstMigrations, []);

    expect($data)
        ->toBe(['step1' => true, '__vn' => 0]);

    $secondMigrations = [
        0 => fn ($data) => [],
        1 => fn (array $data) => array_merge($data, ['step2' => true]),
    ];

    $data = Migrator::migrate($secondMigrations, $data);

    expect($data)
        ->toBe(['step1' => true, '__vn' => 1, 'step2' => true]);
});

it('stores migration versions', function (string $class) {
    $object = new $class;
    $serialized = $this->serializer->serialize($object);
    expect(json_decode($serialized, true)['__vn'])->toBe(0);
})->with([
    'Event' => EventWithMigration::class,
    'State' => StateWithMigration::class,
    'SerializedByVerbs' => DTOWithMigration::class,
]);

class EventWithMigrationDto extends Event
{
    public function __construct(public DTOWithMigration $dto) {}
}

class DTOWithMigration implements SerializedByVerbs, ShouldMigrateData
{
    use NormalizeToPropertiesAndClassName;

    public bool $migrated;

    public function migrations(): Migrations|array
    {
        return [0 => fn ($data) => array_merge($data, ['migrated' => true])];
    }
}

class EventWithMigration extends Event implements ShouldMigrateData
{
    public bool $migrated;

    public function migrations(): array
    {
        return [0 => fn ($data) => array_merge($data, ['migrated' => true])];
    }
}

class StateWithMigration extends State implements ShouldMigrateData
{
    public $migrated;

    public function migrations(): array
    {
        return [0 => fn ($data) => array_merge($data, ['migrated' => true])];
    }
}
