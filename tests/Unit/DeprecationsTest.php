<?php

use Thunk\Verbs\Contracts\StoresEvents;
use Thunk\Verbs\Contracts\StoresSnapshots;
use Thunk\Verbs\Event;
use Thunk\Verbs\Lifecycle\Guards;
use Thunk\Verbs\Lifecycle\MetadataManager;
use Thunk\Verbs\State;
use Thunk\Verbs\State\StateManager;
use Thunk\Verbs\Testing\EventStoreFake;
use Thunk\Verbs\Testing\SnapshotStoreFake;

beforeEach(function () {
    app()->instance(StoresSnapshots::class, new SnapshotStoreFake);
    app()->instance(StoresEvents::class, new EventStoreFake(app(MetadataManager::class)));
});

function captureDeprecations(callable $callback): array
{
    $deprecations = [];

    set_error_handler(function (int $errno, string $errstr) use (&$deprecations) {
        $deprecations[] = $errstr;

        return true;
    }, E_USER_DEPRECATED);

    try {
        $callback();
    } finally {
        restore_error_handler();
    }

    return $deprecations;
}

test('the legacy Lifecycle\StateManager name resolves to the bound manager', function () {
    $legacy = app(Thunk\Verbs\Lifecycle\StateManager::class);

    expect($legacy)->toBe(app(StateManager::class))
        ->and($legacy)->toBeInstanceOf(Thunk\Verbs\Lifecycle\StateManager::class);
});

test('load() accepts the legacy ($id, $type) argument order with a deprecation', function () {
    $state = null;

    $deprecations = captureDeprecations(function () use (&$state) {
        $state = app(StateManager::class)->load(123, DeprecationsTestState::class);
    });

    expect($state)->toBeInstanceOf(DeprecationsTestState::class)
        ->and($state->id)->toBe(123)
        ->and($deprecations)->toHaveCount(1)
        ->and($deprecations[0])->toContain('deprecated');
});

test('make() accepts the legacy ($id, $type) argument order with a deprecation', function () {
    $state = null;

    $deprecations = captureDeprecations(function () use (&$state) {
        $state = app(StateManager::class)->make(456, DeprecationsTestState::class);
    });

    expect($state)->toBeInstanceOf(DeprecationsTestState::class)
        ->and($state->id)->toBe(456)
        ->and($deprecations)->toHaveCount(1);
});

test('reset(include_storage: true) still clears snapshot storage with a deprecation', function () {
    $snapshots = app(StoresSnapshots::class);

    $snapshots->write([DeprecationsTestState::load(1)]);
    $snapshots->assertWritten(DeprecationsTestState::class);

    $deprecations = captureDeprecations(
        fn () => app(StateManager::class)->reset(include_storage: true)
    );

    $snapshots->assertNothingWritten();

    expect($deprecations)->toHaveCount(1);
});

test('Guards::check() still authorizes and validates with a deprecation', function () {
    $guards = null;

    $deprecations = captureDeprecations(function () use (&$guards) {
        $guards = Guards::for(new DeprecationsTestEvent)->check();
    });

    expect($guards)->toBeInstanceOf(Guards::class)
        ->and($deprecations)->toHaveCount(1);
});

class DeprecationsTestState extends State
{
    public int $count = 0;
}

class DeprecationsTestEvent extends Event {}
