<?php

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Thunk\Verbs\Event;
use Thunk\Verbs\Facades\Verbs;
use Thunk\Verbs\Lifecycle\MetadataManager;
use Thunk\Verbs\Support\Wormhole;

test('a callback can be run for a past timestamp', function () {
    $now = now();
    $event = new class extends Event {};
    app(MetadataManager::class)->setEphemeral($event, 'created_at', Date::parse('2023-01-02 00:00:00'));
    app(Wormhole::class)->warp($event, function () use ($now) {
        expect(Carbon::now()->format('Y-m-d'))->toBe('2023-01-02')
            ->and(CarbonImmutable::now()->format('Y-m-d'))->toBe('2023-01-02')
            ->and(now()->format('Y-m-d'))->toBe('2023-01-02')
            ->and(app(Wormhole::class)->realNow()->format('Y-m-d'))->toBe($now->format('Y-m-d'))
            ->and(Verbs::realNow()->format('Y-m-d'))->toBe($now->format('Y-m-d'));
    });
});

test('a callback can be run for a past timestamp with "test now" set', function () {
    Date::setTestNow('2023-06-02 00:00:00');
    $event = new class extends Event {};
    app(MetadataManager::class)->setEphemeral($event, 'created_at', Date::parse('2023-01-02 00:00:00'));
    app(Wormhole::class)->warp($event, function () {
        expect(Carbon::now()->format('Y-m-d'))->toBe('2023-01-02')
            ->and(CarbonImmutable::now()->format('Y-m-d'))->toBe('2023-01-02')
            ->and(now()->format('Y-m-d'))->toBe('2023-01-02')
            ->and(app(Wormhole::class)->realNow()->format('Y-m-d'))->toBe('2023-06-02')
            ->and(Verbs::realNow()->format('Y-m-d'))->toBe('2023-06-02');
    });
});

test('realNow keeps flowing inside a warp when userland time is not mocked', function () {
    $event = new class extends Event {};
    app(MetadataManager::class)->setEphemeral($event, 'created_at', Date::parse('2023-01-02 00:00:00'));

    app(Wormhole::class)->warp($event, function () {
        $first = app(Wormhole::class)->realNow();
        usleep(1_000);
        $second = app(Wormhole::class)->realNow();

        // A frozen capture at warp entry would return the same instant twice.
        expect($second->greaterThan($first))->toBeTrue();
    });

    // The warp's own test-now must clear completely—restoring a *real* now as
    // a test-now here would silently freeze time for the rest of the process.
    expect(Carbon::hasTestNow())->toBeFalse()
        ->and(CarbonImmutable::hasTestNow())->toBeFalse();
});

test('realNow stops reflecting a userland mock once it is cleared after a warp', function () {
    Date::setTestNow('2023-06-02 00:00:00');

    $event = new class extends Event {};
    app(MetadataManager::class)->setEphemeral($event, 'created_at', Date::parse('2023-01-02 00:00:00'));

    app(Wormhole::class)->warp($event, fn () => null);

    // The warp must not hold on to the captured mock: once userland clears
    // its test now, realNow() is flowing wall-clock time again.
    Date::setTestNow();

    expect(app(Wormhole::class)->realNow()->format('Y'))->toBe((new DateTime)->format('Y'));
});

test('a userland test now survives past the end of a warp', function () {
    Date::setTestNow('2023-06-02 00:00:00');

    $event = new class extends Event {};
    app(MetadataManager::class)->setEphemeral($event, 'created_at', Date::parse('2023-01-02 00:00:00'));

    app(Wormhole::class)->warp($event, fn () => null);

    expect(now()->format('Y-m-d H:i:s'))->toBe('2023-06-02 00:00:00')
        ->and(Carbon::now()->format('Y-m-d H:i:s'))->toBe('2023-06-02 00:00:00')
        ->and(CarbonImmutable::now()->format('Y-m-d H:i:s'))->toBe('2023-06-02 00:00:00');
});

test('a closure test now round-trips through a warp', function () {
    $mock = fn () => Carbon::parse('2023-06-02 00:00:00');

    Carbon::setTestNow($mock);
    CarbonImmutable::setTestNow($mock);

    $event = new class extends Event {};
    app(MetadataManager::class)->setEphemeral($event, 'created_at', Date::parse('2023-01-02 00:00:00'));

    app(Wormhole::class)->warp($event, function () {
        expect(now()->format('Y-m-d'))->toBe('2023-01-02')
            ->and(app(Wormhole::class)->realNow()->format('Y-m-d'))->toBe('2023-06-02');
    });

    // The mock is restored by identity, not re-resolved into a frozen instant.
    expect(Carbon::getTestNow())->toBe($mock)
        ->and(Carbon::now()->format('Y-m-d'))->toBe('2023-06-02');
});

test('a dynamic closure test now keeps realNow flowing inside a warp', function () {
    Carbon::setTestNow(fn ($now) => $now->subYear());
    CarbonImmutable::setTestNow(fn ($now) => $now->subYear());

    $event = new class extends Event {};
    app(MetadataManager::class)->setEphemeral($event, 'created_at', Date::parse('2023-01-02 00:00:00'));

    app(Wormhole::class)->warp($event, function () {
        $first = app(Wormhole::class)->realNow();
        usleep(1_000);
        $second = app(Wormhole::class)->realNow();

        // The mock computes from the real now, so realNow() must keep flowing
        // through it rather than freezing at whatever it resolved to at entry.
        expect($second->greaterThan($first))->toBeTrue()
            ->and($first->year)->toBe(((int) (new DateTime)->format('Y')) - 1);
    });
});

test('nested warps preserve the outer realNow and restore it afterward', function () {
    Date::setTestNow('2023-06-02 00:00:00');

    $outer = new class extends Event {};
    $inner = new class extends Event {};

    app(MetadataManager::class)->setEphemeral($outer, 'created_at', Date::parse('2023-01-02 00:00:00'));
    app(MetadataManager::class)->setEphemeral($inner, 'created_at', Date::parse('2022-05-05 00:00:00'));

    app(Wormhole::class)->warp($outer, function () use ($inner) {
        app(Wormhole::class)->warp($inner, function () {
            // The inner warp must not mistake the outer warp's time for the "real" now.
            expect(now()->format('Y-m-d'))->toBe('2022-05-05')
                ->and(app(Wormhole::class)->realNow()->format('Y-m-d'))->toBe('2023-06-02');
        });

        // Back in the outer warp: both the warped now and the real now are intact.
        expect(now()->format('Y-m-d'))->toBe('2023-01-02')
            ->and(app(Wormhole::class)->realNow()->format('Y-m-d'))->toBe('2023-06-02');
    });
});
