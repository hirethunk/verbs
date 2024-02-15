<?php

use Illuminate\Support\Collection;
use Thunk\Verbs\State;
use Thunk\Verbs\StateFactory;

test('a factory can create a state', function () {
    $state = FactoryTestState::factory()->create([
        'name' => 'daniel',
    ]);

    expect($state->name)->toBe('daniel');
});

test('a factory without parameters can create a state', function () {
    $state = FactoryTestState::factory()->create();

    expect($state)->toBeInstanceOf(FactoryTestState::class);
});

test('a factory can accept an id using the create method', function () {
    $state = FactoryTestState::factory()->create([
        'name' => 'daniel',
    ], 1);

    expect($state->id)->toBe(1);
    expect($state->name)->toBe('daniel');
});

test('a factory can accept an id using for method', function () {
    $state = FactoryTestState::factory()->id(1)->create([
        'name' => 'daniel',
    ]);

    expect($state->id)->toBe(1);
    expect($state->name)->toBe('daniel');
});

test('a factory can accept an id using the create method over the for method', function () {
    $state = FactoryTestState::factory()->id(1)->create([
        'name' => 'daniel',
    ], 2);

    expect($state->id)->toBe(2);
    expect($state->name)->toBe('daniel');
});

test('a custom factory can have a default definition', function () {
    expect(CustomFactoryTestState::factory()->create()->name)->toBe('Chris');
});

test('a custom factory can have an array transformation', function () {
    expect(CustomFactoryTestState::factory()->daniel()->create()->name)->toBe('Daniel');
});

test('a custom factory can have a callback transformation', function () {
    expect(CustomFactoryTestState::factory()->john()->create()->name)->toBe('John');
});

test('multiple states can be factoried at once', function () {
    $states = CustomFactoryTestState::factory()->count(3)->create();

    expect($states)->toBeInstanceOf(Collection::class)
        ->and($states->count())->toBe(3)
        ->and($states[1]->id)->toBeGreaterThan($states[0]->id)
        ->and($states[2]->id)->toBeGreaterThan($states[1]->id);
});

class FactoryTestState extends State
{
    public string $name;
}

class CustomFactoryTestState extends State
{
    public string $name;

    protected static function newFactory(Collection $transformations, ?int $count)
    {
        return new class(state_class: static::class, transformations: $transformations, count: $count) extends StateFactory
        {
            public function definition(): array
            {
                return ['name' => 'Chris'];
            }

            public function daniel(): static
            {
                return $this->state(['name' => 'Daniel']);
            }

            public function john(): static
            {
                return $this->state(function (array $data) {
                    expect($data['name'])->toBe('Chris');

                    return ['name' => 'John'];
                });
            }
        };
    }
}
