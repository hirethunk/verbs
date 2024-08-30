<?php

use Thunk\Verbs\Attributes\Migrations\PropertyAdded;
use Thunk\Verbs\Attributes\Migrations\PropertyAddedUsing;
use Thunk\Verbs\Attributes\Migrations\PropertyMigrated;
use Thunk\Verbs\Attributes\Migrations\PropertyMigratedUsing;
use Thunk\Verbs\Attributes\Migrations\PropertyRemoved;
use Thunk\Verbs\Event;
use Thunk\Verbs\Exceptions\MigratorException;
use Thunk\Verbs\SerializedByVerbs;
use Thunk\Verbs\ShouldMigrateData;
use Thunk\Verbs\State;
use Thunk\Verbs\Support\HasMigrations;
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
    'DTO with attribute migrations' => [DtoWithAttributeMigrations::class, ['first_property' => 'initial']],
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
    $data = [
        'existing' => 'data',
    ];
    expect(fn () => Migrator::migrate($migrations, $data))->toThrow(MigratorException::class, $message);
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
    'duplicate attribute' => [new class implements ShouldMigrateData
    {
        use HasMigrations;

        #[PropertyAdded(version: 0, value: 'default_value')]
        public string $added_property;

        #[PropertyAdded(version: 0, value: 'default_value')]
        public string $added_property_duplicate;

        public function __construct() {}
    }, 'Duplicate migration version'],
    'PropertyAdded respects existing data' => [new class implements ShouldMigrateData
    {
        use HasMigrations;

        #[PropertyAdded(version: 0, value: 'default_value')]
        public string $existing;
    }, 'already exists'],
    'PropertyRemoved fails if property does not exist' => [new class implements ShouldMigrateData
    {
        use HasMigrations;

        #[PropertyRemoved(version: 0, property: 'non_existent')]
        public function __construct() {}
    }, 'does not exist'],
    'PropertyMigrated fails if the using method does not exist' => [new class implements ShouldMigrateData
    {
        use HasMigrations;

        #[PropertyMigrated(version: 0, using: 'method')]
        public int $non_existent;

        public function __construct() {}
    }, 'does not exist'],
    'PropertyMigratedUsing function should accept an array' => [new class implements ShouldMigrateData
    {
        use HasMigrations;

        #[PropertyMigratedUsing(version: 0, property: 'property')]
        public function non_existent(string $data): array
        {
            return [];
        }

        public function __construct() {}
    }, 'array and return the new value'],
    'PropertyAddedUsing function should accept an array' => [new class implements ShouldMigrateData
    {
        use HasMigrations;

        #[PropertyAddedUsing(version: 0, property: 'property')]
        public function non_existent(string $data): array
        {
            return [];
        }

        public function __construct() {}
    }, 'array and return the new value'],
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

it('generates migrations from attributes', function () {
    $object = new class
    {
        use HasMigrations;

        #[PropertyAdded(version: 0, value: 'default_value')]
        public string $added_property;

        #[PropertyMigrated(version: 1, using: 'method')]
        public string $migrated_property;

        #[PropertyRemoved(version: 4, property: 'removed_property')]
        public function __construct() {}

        #[PropertyAddedUsing(version: 2, property: 'added_property')]
        #[PropertyMigratedUsing(version: 3, property: 'migrated_property')]
        public function migrateProperty(array $data)
        {
            return $data;
        }
    };

    $migrations = $object->migrations();

    expect($migrations)->toHaveCount(5)->toHaveKeys([0, 1, 2, 3, 4]);
});

it('Property Attributes can migrate data correctly', function (object|string $object, array $expected, array $initial) {
    if (is_string($object)) {
        $object = new $object;
    }
    $migrations = $object->migrations();
    $data = Migrator::migrate($migrations, $initial);
    expect($data)->toBe($expected);
})->with([
    'PropertyAdded' => [new class
    {
        use HasMigrations;

        #[PropertyAdded(version: 0, value: 'default_value')]
        public string $added_property;

        public function __construct() {}
    }, ['added_property' => 'default_value', '__vn' => 0], []],
    'PropertyMigrated' => [new class
    {
        use HasMigrations;

        #[PropertyMigrated(version: 0, using: 'migrateProperty')]
        public string $migrated_property;

        public function migrateProperty(array $data)
        {
            return 'default_value';
        }

        public function __construct() {}
    }, ['migrated_property' => 'default_value', '__vn' => 0], ['migrated_property' => 'previous']],
    'PropertyAddedUsing' => [new class
    {
        use HasMigrations;

        #[PropertyAddedUsing(version: 0, property: 'added_property')]
        public function migrateProperty(array $data)
        {
            return 'default_value';
        }

        public function __construct() {}
    }, ['added_property' => 'default_value', '__vn' => 0], []],
    'PropertyMigratedUsing' => [new class implements ShouldMigrateData
    {
        use HasMigrations;

        #[PropertyMigratedUsing(version: 0, property: 'migrated_property')]
        public function migrateProperty(array $data)
        {
            return 'new_value';
        }
    }, ['migrated_property' => 'new_value', '__vn' => 0], ['migrated_property' => 'old_value']],
    'PropertyRemoved' => [DTOWithPropertyRemovedAttribute::class, ['__vn' => 0], ['removed_property' => 'value']]
]);

// Test the multi-stage migration
it('migrates data correctly through multiple stages', function () {
    $target = new DtoWithAttributeMigrations;

    $migrations = $target->migrations();

    $initialData = ['first_property' => 'initial'];
    $data = Migrator::migrate([$migrations[0]], $initialData);
    expect($data)->toBe(['__vn' => 0]);

    $data = Migrator::migrate([1 => $migrations[1]], $data);
    expect($data)->toBe(['__vn' => 1, 'first_property' => 'replaced']);

    $data = Migrator::migrate([2 => $migrations[2]], $data);
    expect($data)->toBe(['__vn' => 2, 'first_property' => 'replaced_migrated']);

    $data = Migrator::migrate([3 => $migrations[3]], $data);
    expect($data)->toBe(['__vn' => 3, 'first_property' => 'replaced_migrated_twice']);

    $data = Migrator::migrate([4 => $migrations[4]], $data);
    expect($data)->toBe(['__vn' => 4, 'first_property' => 'replaced_migrated_twice', 'migrated' => true]);
});

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

#[PropertyRemoved(0, 'removed_property')]
class DTOWithPropertyRemovedAttribute implements ShouldMigrateData
{
    use HasMigrations;
}

class DtoWithAttributeMigrations implements ShouldMigrateData
{
    use HasMigrations;

    #[PropertyMigrated(version: 2, using: 'migrateProperty')]
    #[PropertyAdded(version: 1, value: 'replaced')]
    public string $first_property;

    public bool $migrated;

    #[PropertyMigratedUsing(version: 3, property: 'first_property')]
    public function secondMigration(array $data)
    {
        return $data['first_property'].'_twice';
    }

    public function migrateProperty(array $data)
    {
        return $data['first_property'].'_migrated';
    }

    #[PropertyAddedUsing(version: 4, property: 'migrated')]
    public function add(array $data)
    {
        return true;
    }

    #[PropertyRemoved(version: 0, property: 'first_property')]
    public function __construct() {}
};
