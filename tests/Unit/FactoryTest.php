<?php

use Thunk\Verbs\State;

test('a factory can accept an id using for method', function () {
    $state = StateWithId::factory()->for(1)->create([
        'name' => 'daniel',
    ]);

    expect($state->id)->toBe(1);
    expect($state->name)->toBe('daniel');
});

class StateWithId extends State
{
    public string $name;
}
