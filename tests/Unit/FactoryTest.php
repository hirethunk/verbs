<?php

use Thunk\Verbs\State;

test('a factory can create a state', function () {
    $state = StateWithId::factory()->create([
        'name' => 'daniel',
    ]);

    expect($state->name)->toBe('daniel');
});

test('a factory without parameters can create a state', function () {
    $state = StateWithId::factory()->create();

    expect($state)->toBeInstanceOf(StateWithId::class);
});

test('a factory can accept an id using the create method', function () {
    $state = StateWithId::factory()->create([
        'name' => 'daniel',
    ], 1);

    expect($state->id)->toBe(1);
    expect($state->name)->toBe('daniel');
});

test('a factory can accept an id using for method', function () {
    $state = StateWithId::factory()->for(1)->create([
        'name' => 'daniel',
    ]);

    expect($state->id)->toBe(1);
    expect($state->name)->toBe('daniel');
});

test('a factory can accept an id using the create method over the for method', function () {
    $state = StateWithId::factory()->for(1)->create([
        'name' => 'daniel',
    ], 2);

    expect($state->id)->toBe(2);
    expect($state->name)->toBe('daniel');
});

class StateWithId extends State
{
    public string $name;
}
