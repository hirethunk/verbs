<?php

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Thunk\Verbs\State;
use Thunk\Verbs\Support\IdManager;

test('state new respects the configured id type', function ($type, callable $check) {
    Config::set('verbs.id_type', $type);

    App::forgetInstance(IdManager::class);

    $state = StateIdGenerationTestState::new();

    expect($state)
        ->toBeInstanceOf(StateIdGenerationTestState::class)
        ->toHaveProperty('id')
        ->and($check((string) $state->id))->toBeTrue();
})->with([
    'ulid' => ['ulid', [Str::class, 'isUlid']],
    'uuid' => ['uuid', [Str::class, 'isUuid']],
    'snowflake' => ['snowflake', 'validate_snowflake'],
]);

function validate_snowflake($value): bool
{
    return preg_match('/^[1-9][0-9]{17}$/', $value);
}

class StateIdGenerationTestState extends State {}
