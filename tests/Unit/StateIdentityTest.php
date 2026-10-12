<?php

use Glhd\Bits\Snowflake;
use Thunk\Verbs\SingletonState;
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

test('it normalizes last_event_id at construction', function () {
    $keyed = fn ($last_event_id) => new StateIdentity(StateIdentityTestState::class, 1, last_event_id: $last_event_id);

    // MySQL/Postgres hand bigints back as strings via PDO—two identities for
    // the same underlying event must be indistinguishable either way.
    expect($keyed('123')->last_event_id)->toBe(123)
        ->and($keyed(123)->last_event_id)->toBe(123)
        ->and($keyed('123')->last_event_id)->toBe($keyed(123)->last_event_id)
        ->and($keyed(null)->last_event_id)->toBeNull();
});

test('it keeps an all-digit ULID last_event_id as a string', function () {
    $all_digit_ulid = '01234567890123456789012345';

    $identity = new StateIdentity(StateIdentityTestState::class, 1, last_event_id: $all_digit_ulid);

    expect($identity->last_event_id)->toBe($all_digit_ulid);
});

test('it normalizes an object last_event_id to its scalar', function () {
    $snowflake = Snowflake::fromId(snowflake_id());

    $identity = new StateIdentity(StateIdentityTestState::class, 1, last_event_id: $snowflake);

    expect($identity->last_event_id)->toBe($snowflake->id());
});

test('it coerces an object state id when building from a state instance', function () {
    $state = new StateIdentityTestState;
    $state->id = Snowflake::fromId(snowflake_id());

    $identity = StateIdentity::from($state);

    expect($identity->state_id)->toBe($state->id->id());
});

test('a singleton keys by its type alone', function () {
    $one = new StateIdentity(StateIdentityTestSingleton::class, snowflake_id());
    $another = new StateIdentity(StateIdentityTestSingleton::class, snowflake_id());

    expect($one->key())->toBe(StateIdentityTestSingleton::class)
        ->and($another->key())->toBe($one->key());
});

test('a keyed state keys as type:id, with int and string ids equivalent', function () {
    $identity = new StateIdentity(StateIdentityTestState::class, 7);

    expect($identity->key())->toBe(StateIdentityTestState::class.':7')
        ->and((new StateIdentity(StateIdentityTestState::class, '7'))->key())->toBe($identity->key());
});

class StateIdentityTestState extends State {}

class StateIdentityTestSingleton extends SingletonState {}
