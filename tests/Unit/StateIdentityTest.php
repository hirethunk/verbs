<?php

use Thunk\Verbs\State;
use Thunk\Verbs\State\StateIdentity;

test('it builds an identity from a state instance', function () {
    $state = new StateIdentityTestState;
    $state->id = 42;

    $identity = StateIdentity::from($state);

    expect($identity->state_type)->toBe(StateIdentityTestState::class)
        ->and($identity->state_id)->toBe(42);
});

test('it returns the same identity it is given', function () {
    $identity = new StateIdentity(StateIdentityTestState::class, 7);

    expect(StateIdentity::from($identity))->toBe($identity);
});

test('it accepts an integer state id from a generic row', function () {
    $identity = StateIdentity::from((object) [
        'state_id' => 10,
        'state_type' => StateIdentityTestState::class,
    ]);

    expect($identity->state_id)->toBe(10);
});

test('it accepts a string state id from a generic row', function () {
    // MySQL/Postgres return bigints as strings via PDO, and UUID-keyed states
    // use string ids—both must be summarizable during reconstitution discovery,
    // not rejected.
    $identity = StateIdentity::from((object) [
        'state_id' => '9007199254740993',
        'state_type' => StateIdentityTestState::class,
    ]);

    expect($identity->state_id)->toBe('9007199254740993');
});

test('it rejects a row without a usable state id', function () {
    StateIdentity::from((object) ['state_type' => StateIdentityTestState::class]);
})->throws(InvalidArgumentException::class);

class StateIdentityTestState extends State {}
