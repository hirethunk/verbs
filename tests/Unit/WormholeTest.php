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

test('nested warps each report their own time while realNow honors userland throughout', function () {
    Date::setTestNow('2023-06-02 00:00:00');

    $outer = new class extends Event {};
    $inner = new class extends Event {};
    app(MetadataManager::class)->setEphemeral($outer, 'created_at', Date::parse('2023-01-02 00:00:00'));
    app(MetadataManager::class)->setEphemeral($inner, 'created_at', Date::parse('2022-05-05 00:00:00'));

    app(Wormhole::class)->warp($outer, function () use ($inner) {
        expect(Carbon::now()->format('Y-m-d'))->toBe('2023-01-02')
            ->and(app(Wormhole::class)->realNow()->format('Y-m-d'))->toBe('2023-06-02');

        app(Wormhole::class)->warp($inner, function () {
            expect(Carbon::now()->format('Y-m-d'))->toBe('2022-05-05')
                ->and(app(Wormhole::class)->realNow()->format('Y-m-d'))->toBe('2023-06-02');
        });

        // Leaving the inner warp restores the outer warp's time, and realNow()
        // still reports userland's mock rather than the inner warp's time.
        expect(Carbon::now()->format('Y-m-d'))->toBe('2023-01-02')
            ->and(app(Wormhole::class)->realNow()->format('Y-m-d'))->toBe('2023-06-02');
    });

    // Fully unwound: userland's mock is back in place.
    expect(Carbon::now()->format('Y-m-d'))->toBe('2023-06-02');
});

test('realNow resolves a closure-based "test now" during a warp', function () {
    Date::setTestNow(fn () => Date::parse('2023-06-02 12:34:56'));

    $event = new class extends Event {};
    app(MetadataManager::class)->setEphemeral($event, 'created_at', Date::parse('2023-01-02 00:00:00'));

    app(Wormhole::class)->warp($event, function () {
        expect(Carbon::now()->format('Y-m-d'))->toBe('2023-01-02')
            ->and(app(Wormhole::class)->realNow()->format('Y-m-d H:i:s'))->toBe('2023-06-02 12:34:56')
            ->and(Verbs::realNow()->format('Y-m-d H:i:s'))->toBe('2023-06-02 12:34:56');
    });
});

test('realNow tracks the live clock during a warp when time is not mocked', function () {
    $event = new class extends Event {};
    app(MetadataManager::class)->setEphemeral($event, 'created_at', Date::parse('2000-01-01 00:00:00'));

    app(Wormhole::class)->warp($event, function () {
        // now() is warped to the year 2000, but realNow() should report the
        // real wall-clock year (date() is unaffected by Carbon's test now).
        expect(Carbon::now()->format('Y'))->toBe('2000')
            ->and(app(Wormhole::class)->realNow()->format('Y'))->toBe(date('Y'));
    });
});
